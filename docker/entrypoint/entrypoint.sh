#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Container entrypoint for the application image.
#
# Runtime work only: migrations need a live database, and the config/route/view
# caches must capture the real environment, not build-time placeholders.
# ---------------------------------------------------------------------------

log() { printf '[entrypoint] %s\n' "$*"; }

# ---------------------------------------------------------------------------
# Container role.
#
# One image, three roles: the web tier (nginx + php-fpm), a queue worker, and
# the scheduler. Each runs as its own container with its own supervisor program
# set, so a wedged worker cannot stop requests being served and a web restart
# cannot kill a job mid-flight.
#
# Only the web role owns the database: migrations and seeding run there and
# nowhere else. Two containers starting together would otherwise race the same
# migration, and Laravel's --isolated lock lives in the cache table that the
# very first migration creates.
# ---------------------------------------------------------------------------
CONTAINER_ROLE="${CONTAINER_ROLE:-app}"

case "${CONTAINER_ROLE}" in
    app|worker|scheduler) ;;
    *)
        log "FATAL: unknown CONTAINER_ROLE '${CONTAINER_ROLE}'."
        log "       Expected one of: app, worker, scheduler."
        exit 1
        ;;
esac

log "Container role: ${CONTAINER_ROLE}."

# Non-web roles never migrate or seed, whatever the environment says. These are
# defaults for the *web* role; the override below is unconditional on purpose.
if [ "${CONTAINER_ROLE}" != "app" ]; then
    RUN_MIGRATIONS="false"
    RUN_SEEDERS="false"
fi

# The Dockerfile creates this, but a volume or tmpfs mounted over /tmp can wipe
# it. php.ini points opcache.file_cache here and PHP treats a missing directory
# as a startup FATAL — every artisan call below would die before booting.
if [ ! -d /tmp/opcache ]; then
    log "Recreating /tmp/opcache (CLI opcache file cache)."
    mkdir -p /tmp/opcache && chown www-data:www-data /tmp/opcache || \
        log "WARNING: could not create /tmp/opcache; PHP may fail to start."
fi

if [ -z "${APP_KEY:-}" ]; then
    log "FATAL: APP_KEY is not set. Generate one with:"
    log "       php artisan key:generate --show"
    exit 1
fi

# Compose expands '$' inside environment values, so a password like 'Pas$W0rd'
# arrives mangled as 'Pas!'. Flag values that still contain a '$' so the
# operator double-checks; see docker/README.md.
for secret in DB_PASSWORD; do
    eval "value=\${$secret:-}"
    if [ -n "${value:-}" ] && [ "${value}" != "${value#*\$}" ]; then
        log "NOTE: ${secret} contains a literal '\$'. If you did not escape it as"
        log "      '\$\$' in Dokploy, Compose will have eaten part of it. Confirm with:"
        log "      docker compose exec -T app printenv ${secret}"
    fi
done

# Wait for the database. Compose depends_on covers the common case, but Dokploy
# may start the managed MariaDB in a looser order.
if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    log "Waiting for database at ${DB_HOST:-mysql}:${DB_PORT:-3306} ..."
    for i in $(seq 1 60); do
        if php -r '
            $h=getenv("DB_HOST")?:"mysql"; $p=getenv("DB_PORT")?:"3306";
            $c=@fsockopen($h,(int)$p,$e,$s,2);
            exit($c ? 0 : 1);
        '; then
            log "Database reachable."
            break
        fi
        if [ "$i" = "60" ]; then
            log "FATAL: database unreachable after 60 attempts."
            exit 1
        fi
        sleep 2
    done
fi

# Migrations run in the web role only (enforced above). The gate remains so a
# second web replica could be added without both racing migrations.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    log "Running migrations ..."

    # --isolated takes its lock through the cache store, and CACHE_STORE is
    # "database" — on a FRESH database that lock needs the cache_locks table
    # the migrations have not created yet. Probe first; the unisolated first
    # run is safe because there is nothing to race over.
    if php artisan db:table cache_locks --json >/dev/null 2>&1; then
        MIGRATE_ISOLATION="--isolated"
    else
        MIGRATE_ISOLATION=""
        log "cache_locks absent (fresh database); migrating without isolation."
    fi

    # shellcheck disable=SC2086 — intentionally unquoted.
    php artisan migrate --force $MIGRATE_ISOLATION || {
        log "FATAL: migrations failed."
        exit 1
    }
fi

# Seeding. AdminUserSeeder is idempotent (an existing admin address is promoted,
# never reset) and DatabaseSeeder's demo user is skipped once it exists, so this
# is safe to leave enabled across redeploys. The demo user is seeded in every
# environment but production, matching where the quick logins are offered.
#
# The admin credentials are fixed demo values in config/first.php, so there is
# nothing to configure and nothing that can be forgotten here.
if [ "${RUN_SEEDERS:-true}" = "true" ]; then
    log "Seeding (idempotent) ..."
    php artisan db:seed --force || {
        log "FATAL: db:seed failed."
        exit 1
    }
fi

# A worker or scheduler that boots before the web container has migrated would
# crash on its first query against a table that does not exist yet, and on a
# slow first deploy it could crashloop past Docker's restart backoff. Wait for
# the queue table the role actually depends on instead of starting blind.
if [ "${CONTAINER_ROLE}" != "app" ] && [ "${WAIT_FOR_MIGRATIONS:-true}" = "true" ]; then
    log "Waiting for migrations to be applied by the web container ..."
    for i in $(seq 1 150); do
        if php artisan db:table jobs --json >/dev/null 2>&1; then
            log "Schema ready."
            break
        fi
        if [ "$i" = "150" ]; then
            log "FATAL: schema not ready after 5 minutes. Is the app container healthy?"
            exit 1
        fi
        sleep 2
    done
fi

log "Rebuilding caches for the live environment ..."

# Flush anything stale, tolerate failure on a fresh boot (nothing to clear).
php artisan optimize:clear || log "WARNING: optimize:clear failed (continuing)."

# Cache config, routes and events against the real environment.
php artisan optimize

# Asset work serves HTTP only. A worker renders no panel and serves nothing
# from public/, so skipping this keeps worker boots quick -- and keeps three
# containers from writing the same files concurrently on a redeploy.
if [ "${CONTAINER_ROLE}" = "app" ]; then
    # Filament's compiled CSS/JS and the Inter font are version-locked to the
    # vendor tree; republishing is instant and keeps the panel self-consistent.
    php artisan filament:assets || log "WARNING: filament:assets failed (continuing)."
    php artisan filament:optimize || log "WARNING: filament:optimize failed (continuing)."

    # Storage symlink: recreated every boot because public/ is a fresh image layer.
    php artisan storage:link --force || log "WARNING: storage:link failed (continuing)."

    # Audit the configuration this container ACTUALLY loaded.
    #
    # .env.production.example only helps the person who copies it; this catches
    # what an example file cannot -- a platform-injected variable, a stale env
    # carried over from a previous deploy, APP_DEBUG left true while debugging
    # an incident. App\Console\Commands\CheckProductionConfig reads config()
    # rather than env() throughout, which is why it runs HERE, after `optimize`
    # has written the config cache: run before it, every check would read the
    # uncached values and report on a configuration that is not the live one.
    #
    # WARN-ONLY, deliberately. The command exits non-zero only on hard failures
    # (APP_DEBUG=true, empty APP_KEY) and passes on warnings, so failing the
    # boot here would be defensible -- but a container that refuses to start is
    # strictly harder to diagnose than one that starts and says what is wrong,
    # and a false positive would take the site down rather than flag it. The
    # findings land in the container log where a deploy can be read back.
    #
    # It no-ops on APP_ENV=local/testing by design; a staging instance is
    # audited exactly as production is.
    php artisan app:check-production || \
        log "WARNING: app:check-production reported problems (see its output above)."
fi

# The steps above ran as root; php-fpm serves as www-data and Blade writes to
# storage/framework/views at runtime for any view the cache misses. Only the
# DIRECTORIES need fixing (files are already www-data-owned), so no slow -R.
for d in /var/www/html/bootstrap/cache \
         /var/www/html/storage/framework \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app \
         /var/www/html/storage/app/private \
         /var/www/html/storage/app/public; do
    mkdir -p "$d" 2>/dev/null || true
    chown www-data:www-data "$d" 2>/dev/null || \
        log "WARNING: could not set ownership on $d (continuing)."
done

# The log FILE, not just the directory it sits in.
#
# The loop above assumes a writable directory is enough, which holds for every
# cache under storage/framework: those are keyed by hash, so a file php-fpm
# cannot write is simply rewritten under a new name. The log is the exception —
# `single` and `daily` both append to a FIXED filename, so an unwritable
# laravel.log cannot be routed around.
#
# That is exactly what the Debian->Alpine move produced. www-data went from uid
# 33 to uid 82, storage/logs is a persistent volume, and laravel.log stayed
# uid 33 mode 664: the directory is www-data and writable, the file is not.
# Monolog then fails to open it and Laravel drops the line.
#
# The failure mode is the dangerous part: the site behaves normally and the log
# simply STOPS, so it reads as "quiet" rather than "broken". On a sibling image
# it hid an upload outage for days — 500s with an empty laravel.log to explain
# them.
find /var/www/html/storage/logs -maxdepth 1 -type f ! -user www-data \
    -exec chown www-data:www-data {} + 2>/dev/null || \
    log "WARNING: could not repair log ownership (continuing)."

# The loop above fixes the storage MOUNT POINTS. It does not fix what is
# already inside them, on the reasoning that files there are already
# www-data-owned — which holds right up until www-data stops meaning the same
# uid.
#
# Moving these images from Debian to Alpine changed www-data from uid 33 to uid
# 82. storage/app/private and storage/app/public are persistent volumes —
# declared in x-app-base in BOTH compose files, since uploads land there and an
# image-layer directory would be wiped by every deploy — so the per-resource
# upload directories inside them survive a rebuild still owned by uid 33 at
# mode 755 — owner-only write, and php-fpm is no longer that owner. The symptom is
# specific and quiet: records that carry no file still save, but attaching any
# image fails because Livewire cannot write its temp upload, Filament reports
# only "There was an error while attempting to load this page", and nothing
# reaches laravel.log because the failure is below the application.
#
# So: repair the ownership of the per-resource directories, not just the mount
# points. Two properties keep this cheap enough to run before nginx starts:
#
#   - `! -user www-data` means a correctly-owned tree matches nothing and the
#     loop body never runs. Steady-state cost is one stat per directory, not
#     the recursive walk the comment above rules out.
#   - -maxdepth 2 covers the per-resource directories without descending into
#     their contents, which is where the file count actually lives. Those are
#     created by www-data at runtime and inherit the right owner.
#
# The test is `-user www-data`, never a hardcoded 82. Pinning the number would
# just trade uid 33 for the next base-image surprise; resolving the NAME means
# this repairs itself on any future uid change, which is the whole point.
#
# FILES are repaired as well as directories, and the depth limit is gone. An
# earlier version fixed only `-type d` at -maxdepth 2, on the reasoning that a
# writable directory is enough. It is enough to CREATE a file, which is why new
# uploads started working — but not to overwrite or delete an existing one, so
# replacing a file already in the catalogue still failed the same way. The
# stranded files are uid 33 mode 644.
#
# Dropping the depth limit is safe because `! -user www-data` makes this a
# stat-only walk that matches nothing in the steady state. Measured on a
# production volume: 0.07s over 2,400 entries with every one stale, 0.05s with
# none. Cost tracks the file COUNT, not bytes — a sibling storing 5G of
# archives holds 29 entries and measures 0.00s. The "many seconds" warned about
# elsewhere came from an unfiltered `chown -R`, which pays a syscall per file.
for root in /var/www/html/storage/app/private \
            /var/www/html/storage/app/public; do
    [ -d "$root" ] || continue
    find "$root" -mindepth 1 ! -user www-data \
        -exec chown www-data:www-data {} + 2>/dev/null || \
        log "WARNING: could not repair ownership under $root (continuing)."
done

if [ -f /var/www/html/public/hot ]; then
    log "WARNING: removing stale public/hot (Vite dev-server marker)."
    rm -f /var/www/html/public/hot
fi

# ---------------------------------------------------------------------------
# Hand off to the role's process set.
#
# The image ships one supervisor program set per role under /etc/supervisor/
# roles/. They are NOT in conf.d/, because the distro supervisord.conf ends
# with `[include] files = /etc/supervisor/conf.d/*.conf` -- anything left there
# is loaded by every container, so a worker would also start nginx. Exactly one
# role file is installed into conf.d/ here.
#
# Selecting the role at runtime (rather than giving each compose service its own
# CMD) keeps the services identical apart from CONTAINER_ROLE, so a role cannot
# be started with another role's processes by editing one and forgetting the
# other.
# ---------------------------------------------------------------------------
ROLE_CONF="/etc/supervisor/roles/${CONTAINER_ROLE}.conf"

if [ ! -f "${ROLE_CONF}" ]; then
    log "FATAL: no supervisor program set for role '${CONTAINER_ROLE}' (${ROLE_CONF})."
    exit 1
fi

# Clear any previously installed role (a container restarted with a changed
# CONTAINER_ROLE would otherwise run both), then install this one.
rm -f /etc/supervisor/conf.d/*.conf
mkdir -p /etc/supervisor/conf.d
cp "${ROLE_CONF}" /etc/supervisor/conf.d/role.conf

case "${CONTAINER_ROLE}" in
    app)       log "Starting web role (nginx + php-fpm)." ;;
    worker)    log "Starting queue worker role (queue:work)." ;;
    scheduler) log "Starting scheduler role (schedule:work)." ;;
esac

exec "$@"
