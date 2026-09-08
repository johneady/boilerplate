#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Container entrypoint for the application image.
#
# Runtime work only: migrations need a live database, and the config/route/view
# caches must capture the real environment, not build-time placeholders.
# ---------------------------------------------------------------------------

log() { printf '[entrypoint] %s\n' "$*"; }

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

# Only one web role in this stack, but keep the gate so a second replica could
# be added without both racing migrations.
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

log "Rebuilding caches for the live environment ..."

# Flush anything stale, tolerate failure on a fresh boot (nothing to clear).
php artisan optimize:clear || log "WARNING: optimize:clear failed (continuing)."

# Cache config, routes and events against the real environment.
php artisan optimize

# Filament's compiled CSS/JS and the Inter font are version-locked to the
# vendor tree; republishing is instant and keeps the panel self-consistent.
php artisan filament:assets || log "WARNING: filament:assets failed (continuing)."
php artisan filament:optimize || log "WARNING: filament:optimize failed (continuing)."

# Storage symlink: recreated every boot because public/ is a fresh image layer.
php artisan storage:link --force || log "WARNING: storage:link failed (continuing)."

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

log "Starting web role (nginx + php-fpm)."
exec "$@"
