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

# Seeding. AdminUserSeeder is idempotent (it returns early when the configured
# admin email already exists) and DatabaseSeeder only adds its local/testing
# user outside production, so this is safe to leave enabled across redeploys.
#
# The seeder REFUSES to run with the default admin credentials outside
# local/testing, so a production deploy must set FIRST_USER_EMAIL and a strong
# FIRST_USER_PASSWORD or this step fails loudly.
if [ "${RUN_SEEDERS:-true}" = "true" ]; then
    log "Seeding (idempotent) ..."
    php artisan db:seed --force || {
        log "FATAL: db:seed failed. If this is production, confirm FIRST_USER_EMAIL"
        log "       and FIRST_USER_PASSWORD are set to non-default values."
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
