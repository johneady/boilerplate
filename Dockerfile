# syntax=docker/dockerfile:1.7

# ---------------------------------------------------------------------------
# Application container image.
#
# Debian bookworm throughout, deliberately NOT Alpine: the JS toolchain pins
# glibc (gnu) builds of esbuild/rolldown optional deps, which do not run on
# musl. composer.json allows php ^8.3 and the lock was solved against the
# local PHP 8.5, so the base images are 8.5.
#
# Runtime extensions cover composer.lock's ext-* constraints (intl, zip + the
# bundled dom/xml/mbstring/etc.), plus pdo_mysql for the managed MariaDB. No
# pdo_sqlite: the container always runs against MariaDB (DB_CONNECTION is set
# in both compose files), and SQLite is a local-development-only default.
# ---------------------------------------------------------------------------

# --- Stage 1: PHP dependencies ---------------------------------------------
FROM php:8.5-cli-bookworm AS vendor

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libxml2-dev \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Manifests first so this layer caches until dependencies change.
COPY composer.json composer.lock ./

# --no-scripts: post-autoload-dump runs artisan package:discover, which cannot
# work here (no application in the context yet). The entrypoint regenerates the
# discovery cache at runtime.
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# --- Stage 2: frontend assets ----------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund

# The build tool (vite-plus) reads Blade/PHP sources for Tailwind class
# detection, so this stage needs the application source, not just resources/.
COPY resources ./resources
COPY vite.config.js ./
COPY app ./app
COPY config ./config
COPY routes ./routes

# Tailwind v4 @source directives in resources/css/app.css reach into the
# vendor tree (livewire/flux stubs, framework pagination views) and every one
# of them must resolve or Tailwind silently emits a stylesheet missing the
# utilities those components use.
COPY --from=vendor /app/vendor/livewire ./vendor/livewire
COPY --from=vendor /app/vendor/laravel/framework ./vendor/laravel/framework

# Flux ships stubs that resources/css/app.css reaches into via @source, and
# @import pulls in flux.css itself; both must resolve or Tailwind silently
# emits a stylesheet missing the utilities those components use.
COPY --from=vendor /app/vendor/filament ./vendor/filament

RUN npm run build

# --- Stage 3: runtime -------------------------------------------------------
FROM php:8.5-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor curl \
        libzip-dev libicu-dev libxml2-dev \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# The CLI opcache file_cache directory. PHP treats a missing or unwritable
# opcache.file_cache as a startup FATAL rather than a warning, which would
# kill every artisan call in the entrypoint before the app booted.
RUN mkdir -p /tmp/opcache && chown www-data:www-data /tmp/opcache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
# Role program sets live OUTSIDE conf.d/ deliberately. The distro
# supervisord.conf ends with `[include] files = /etc/supervisor/conf.d/*.conf`,
# so anything dropped in there is loaded by EVERY container -- a worker would
# start nginx and all three roles would run everywhere. The entrypoint copies
# exactly one of these into conf.d/ based on CONTAINER_ROLE.
COPY docker/entrypoint/supervisord.conf /etc/supervisor/roles/app.conf
COPY docker/entrypoint/supervisord.worker.conf /etc/supervisor/roles/worker.conf
COPY docker/entrypoint/supervisord.scheduler.conf /etc/supervisor/roles/scheduler.conf

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# If public/hot ever slips into the context, @vite points asset URLs at the
# visitor's localhost:5173 and the frontend silently dies. Kill it here.
RUN rm -f public/hot

# Generated WITH dev dependencies locally; they name providers absent from a
# --no-dev vendor tree and break every artisan call. Regenerated at runtime.
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
          bootstrap/cache/config.php bootstrap/cache/routes-*.php

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
