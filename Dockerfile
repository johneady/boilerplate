# syntax=docker/dockerfile:1.7

# ---------------------------------------------------------------------------
# Application container image.
#
# Debian bookworm throughout, deliberately NOT Alpine: the JS toolchain pins
# glibc (gnu) builds of esbuild/rolldown optional deps, which do not run on
# musl. composer.json allows php ^8.3 and the lock was solved against the
# local PHP 8.5, so the base images are 8.5.
#
# Extensions are COMPILED from the official php: images rather than installed
# from Debian/sury packages. Compiling is slow (~6 min of cc), which is why
# this image is built in CI and pulled by the deploy target -- see
# .github/workflows/docker.yml. Building on the deploy host instead makes
# every deploy compete with production for RAM, and the compile is where a
# 2-core box runs out of it. The trade is deliberate: the official images can
# be pinned by digest (below) and give bit-identical rebuilds forever, while
# sury keeps only the latest 8.5.x patch and would rewrite the FPM/nginx
# layout (socket vs TCP, per-SAPI conf.d, php-fpm8.5 binary name) on top.
#
# Base images are pinned by DIGEST, not just tag. A floating tag moving under
# you silently re-runs the whole extension compile (cache miss) and changes
# the runtime out from under a rebuild of an old release. Dependabot's docker
# ecosystem updates these digests monthly -- see .github/dependabot.yml.
# ---------------------------------------------------------------------------

# --- Stage 0: shared PHP base ----------------------------------------------
# Both the vendor stage and the runtime stage derive from here, so the shared
# extension set is written ONCE. This is load-bearing for composer: the vendor
# stage's `composer install` validates composer.lock's platform requirements
# against the extensions present in ITS image, so if that set drifts below
# production's, the lock is validated against something production does not
# run. The hard requirements from `composer check-platform-reqs` are intl and
# zip (everything else the lock names is bundled or a suggestion); runtime
# adds pdo_mysql, gd and redis on top for reasons documented in that stage.
#
# php:8.5-fpm-bookworm @ PHP 8.5.10
FROM php:8.5-fpm-bookworm@sha256:8e780a6e59508f418c7729681468322a2ce7d7cfe4266025054f41bbe85e3928 AS php-base

# DEBIAN_FRONTEND is set per-RUN rather than as an ENV: as an ENV it leaks into
# the final image and into every `docker exec`, where an interactive apt then
# silently skips prompts it should have asked.
RUN DEBIAN_FRONTEND=noninteractive apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        libzip-dev libicu-dev libxml2-dev \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# --- Stage 1: PHP dependencies ---------------------------------------------
FROM php-base AS vendor

RUN DEBIAN_FRONTEND=noninteractive apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        git unzip \
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
# node:24-bookworm-slim @ Node v24.21.0. Pinned to the current LTS line; see
# the dependabot ignore for why the major does not move automatically.
FROM node:24-bookworm-slim@sha256:2fe369e969550cde8e867afc3fe370b260140cab4a23d467074295b42163d553 AS assets

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
FROM php-base AS runtime

# intl and zip come from php-base. Everything here is runtime-only:
#
#   pdo_mysql  -- the managed MariaDB. No pdo_sqlite: the container always runs
#                 against MariaDB (DB_CONNECTION is set in both compose files),
#                 and SQLite is a local-development-only default.
#   gd         -- built --with-jpeg --with-webp --with-freetype because
#                 intervention/image decodes and re-encodes user uploads
#                 through it. gd compiles WITHOUT those formats by default and
#                 fails only at runtime, on the worker, when a real photo
#                 arrives -- so the configure flags are load-bearing, not
#                 decoration. The assertion at the end of this stage is what
#                 turns a dropped flag into a failed build.
#   redis      -- from PECL, so it needs `pecl install` + `docker-php-ext-enable`
#                 rather than docker-php-ext-install (which only knows bundled
#                 extensions).
#
#                 Installed even though every default is `database`: the
#                 extension is what makes REDIS a flip of the env rather than
#                 an image rebuild. Without it, setting CACHE_STORE=redis on a
#                 running deployment fails at boot with "please make sure the
#                 PHP Redis extension is installed" -- at which point the
#                 person flipping it is already in production wondering why.
#
#                 ~2MB in the image and nothing loads it while the drivers stay
#                 on database, so the cost of carrying it is close to zero.
#
# ca-certificates is NOT installed here: php:8.5-fpm-bookworm already ships it
# (verified), unlike debian:*-slim, where outbound HTTPS from PHP fails without
# it. Check before adding it if this image is ever rebased on a slim variant.
RUN DEBIAN_FRONTEND=noninteractive apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        nginx supervisor curl \
        libjpeg-dev libpng-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
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

# Fail the BUILD on a missing extension rather than production.
#
# Every trap this guards against is silent at build time and loud much later:
# a dropped `docker-php-ext-configure gd` flag surfaces on the worker when a
# real photo arrives; a missing pdo_mysql surfaces at the first query; a
# missing redis surfaces only once someone flips CACHE_STORE. gd is checked
# per FORMAT, not just for the extension, because that is the flag that gets
# dropped. Runs as a separate layer so the failure names this step.
#
# Placed AFTER the config copies above so it validates the shipped php.ini too,
# not just the compile: opcache.enable is asserted, so an ini that fails to
# apply (wrong conf.d path, typo'd directive) fails the build here rather than
# silently serving every request uncompiled.
#
# extension_loaded(), NOT `php -m | grep`, and note OPcache is spelled
# "Zend OPcache": it is a Zend extension, and BOTH `php -m | grep -ix opcache`
# and extension_loaded("opcache") report it missing on an image that has it
# loaded and enabled. Verified against this base. Do not "tidy" that string to
# lowercase opcache -- that turns this line into a guard that always fails.
RUN set -eu; \
    php -r 'foreach (["intl", "zip", "pdo_mysql", "gd", "redis", "Zend OPcache"] as $e) { if (! extension_loaded($e)) { fwrite(STDERR, "FATAL: php extension \"$e\" missing from image\n"); exit(1); } } \
        $i = gd_info(); \
        foreach (["JPEG Support", "PNG Support", "WebP Support", "FreeType Support"] as $f) { if (empty($i[$f])) { fwrite(STDERR, "FATAL: gd built without $f\n"); exit(1); } } \
        if (! ini_get("opcache.enable")) { fwrite(STDERR, "FATAL: opcache present but not enabled -- check docker/php/php.ini reached conf.d\n"); exit(1); } \
        echo "extension check passed: intl zip pdo_mysql gd(jpeg,png,webp,freetype) redis opcache(enabled)\n";'

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
