# syntax=docker/dockerfile:1.7

# ---------------------------------------------------------------------------
# Application container image.
#
# ALPINE (musl) throughout. This image was on Debian bookworm until the size
# became the problem: 757MB on disk, 229MB per deploy pull. It is now 272MB /
# 83MB. Almost all of that came from the base, not from anything we ship.
#
# Why the Debian base was so much bigger, since it is not obvious:
#
#   1. php:8.5-fpm-bookworm installs the C toolchain (gcc, g++, cpp, binutils,
#      libc6-dev -- ~190MB) PERMANENTLY, in one 316MB layer, deliberately, so
#      that `docker-php-ext-install` and `pecl install` keep working against
#      the image later. php:8.5-fpm-alpine installs the same toolchain into a
#      throwaway `.build-deps` virtual package and `apk del`s it in the SAME
#      RUN, so the compiler never reaches a persisted layer.
#
#   2. On Debian you CANNOT get those 190MB back by removing the packages.
#      A delete in a child layer cannot shrink a parent layer -- it only writes
#      whiteout entries, so the bytes still ship AND the delete costs ~1.3MB
#      more. Measured, not assumed. The only Debian escape is flattening the
#      whole stage onto scratch, which throws away layer sharing and every bit
#      of image metadata (see the STOPSIGNAL note below for why that bites).
#      On Alpine the problem simply never exists.
#
#   3. musl + busybox instead of glibc + GNU coreutils, and no perl (29MB) or
#      python3 (14MB) in the base at all. Nothing here used either.
#
# The JS toolchain no longer blocks this. The previous note here said the
# esbuild/rolldown optional deps were glibc-only; that is stale. vite-plus,
# @tailwindcss/oxide, lightningcss, oxlint and oxfmt all publish -musl builds
# and all of them are already resolved in package-lock.json. Verified the real
# check, not the manifest: `npm run build` on node:24-alpine produces assets
# whose md5sums are IDENTICAL to the Debian build, file for file.
#
# What musl actually costs, so the next person can weigh it honestly:
#   - Different allocator and much smaller default thread stacks than glibc.
#     Nothing in this stack tripped on it, but this is the classic source of
#     "fine on Debian, segfaults in production" for PHP extensions. Exposure is
#     low while this stays pure PHP plus the standard extensions below; it goes
#     up the moment an exotic PECL extension is added.
#   - ICU 78.1 here vs 72.1 on bookworm. Currency, date, collation and
#     transliteration output was diffed across both and is identical, but an
#     ICU major CAN change collation ordering -- worth re-checking if user-
#     facing lists are ever sorted through Collator.
#   - Alpine and Debian disagree on where nginx and supervisor keep their
#     config. Both bit during this migration and both are fixed in the runtime
#     stage below; read those comments before touching either.
#
# Extensions are COMPILED from the official php: images rather than installed
# from distro packages. Compiling is slow (~6 min of cc), which is why this
# image is built in CI and pulled by the deploy target -- see
# .github/workflows/docker.yml. Building on the deploy host instead makes
# every deploy compete with production for RAM, and the compile is where a
# 2-core box runs out of it. The trade is deliberate: the official images can
# be pinned by digest (below) and give bit-identical rebuilds forever.
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
# php:8.5-fpm-alpine @ PHP 8.5.10
FROM php:8.5-fpm-alpine@sha256:630c234abe38c0e9e4726ff59d5af6fc8f573e35939b143580129f2405ea8a74 AS php-base

# .build-deps is the whole reason this image is small: $PHPIZE_DEPS (the base's
# own name for autoconf/gcc/g++/make/pkgconf/re2c) plus the -dev headers go in
# as a VIRTUAL package and come out again in the same RUN, so the compiler is
# never written to a persisted layer. Deleting them in a later RUN would not
# work -- see point 2 in the header.
#
# scanelf walks the .so files that were just built and asks which shared
# libraries they actually link against, so those get re-added as real runtime
# deps (so:libicuuc.so.78 and friends) before the toolchain is removed. Without
# that pass `apk del .build-deps` takes icu-dev's libraries with it and intl
# fails to load -- which the assertion in the runtime stage would catch, but
# only after a 6-minute build.
RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libxml2-dev; \
    docker-php-ext-install intl zip; \
    runDeps="$(scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
        | tr ',' '\n' | sort -u | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }')"; \
    apk add --no-cache $runDeps; \
    apk del --no-network .build-deps

# --- Stage 1: PHP dependencies ---------------------------------------------
FROM php-base AS vendor

RUN apk add --no-cache git unzip

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
# node:24-alpine @ Node v24.21.0. Pinned to the current LTS line; see the
# dependabot ignore for why the major does not move automatically. musl is safe
# here: every native optional dep in package-lock.json ships a -musl build, and
# the emitted assets are md5-identical to the Debian build.
FROM node:24-alpine@sha256:50c8e8ca1d27439048670df5883f32d57cf81cff6233222c893fd0d9884cbd81 AS assets

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

# bash is not in the Alpine base (busybox ash only). The entrypoint is
# #!/usr/bin/env bash; it uses no bash-only syntax today, but installing bash
# (~1MB) keeps that script portable between this image and a developer shell
# rather than making every future edit watch its step around ash.
RUN apk add --no-cache nginx supervisor curl bash

# Two users serve this app: php-fpm runs as www-data and WRITES the uploads,
# while nginx runs as its own `nginx` user and is what READS them back out for
# every static file request. PHP is not in the request path for those, so a
# permission problem here never reaches laravel.log — it surfaces only in the
# nginx error log, as a 403/404 on a file the upload just reported saving
# successfully, with previously-uploaded files still working. That reads like a
# CDN or cache fault, not a permissions one.
#
# Alpine's nginx package already adds its user to the www-data group (verified:
# `id nginx` gives groups=nginx,www-data), so group-read is sufficient and
# uploads do NOT depend on the world-readable bit of mode 0644. A file written
# 0640, or a directory tightened to 0750, still serves correctly.
#
# No `addgroup` is needed here, and adding one would be a no-op. This comment
# exists because the arrangement is easy to misread as "nginx only works
# because uploads happen to be world-readable" and then to over-correct. If a
# future base image drops that group membership, THAT is when uploads start
# 404ing after a permission change, and this is the note that explains why.

# Alpine and Debian put supervisor's config in DIFFERENT places, and the
# difference is silent until the container crash-loops:
#   Alpine: /etc/supervisord.conf          + [include] /etc/supervisor.d/*.ini
#   Debian: /etc/supervisor/supervisord.conf + [include] /etc/supervisor/conf.d/*.conf
# The entrypoint, the CMD in this file and the three role files all speak the
# Debian layout, so rebuild that layout here instead of forking them. Hit for
# real during the Alpine migration: supervisord exited with "could not find
# config file /etc/supervisor/supervisord.conf" on every restart.
#
# The trailing grep is the guard: if an Alpine supervisor update ever changes
# that [include] line, the sed silently matches nothing and every role would
# start with NO programs -- a container that boots, looks healthy to Docker for
# 40s, and runs nothing. Fail the build instead.
RUN set -eux; \
    mkdir -p /etc/supervisor/conf.d; \
    sed -e 's#^files = /etc/supervisor.d/\*\.ini#files = /etc/supervisor/conf.d/*.conf#' \
        /etc/supervisord.conf > /etc/supervisor/supervisord.conf; \
    grep -q '^files = /etc/supervisor/conf.d/\*\.conf$' /etc/supervisor/supervisord.conf

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
#   pcntl      -- what makes the worker role's shutdown contract real.
#                 queue:work registers its SIGTERM handler and the per-job
#                 $timeout alarm through pcntl_signal/pcntl_alarm, and Laravel
#                 SILENTLY skips both when the extension is absent: supervisord's
#                 stopwaitsecs then buys nothing (the process dies mid-job on
#                 the first SIGTERM) and App\Jobs\Job::$timeout is never
#                 enforced. Nothing logs either omission.
#   bcmath     -- arbitrary-precision decimal arithmetic. Five of the eight
#                 projects derived from this boilerplate added it; the first
#                 time money or quantities are handled is the wrong time to
#                 discover floats.
#   exif       -- orientation and metadata from uploaded photos. Pairs with gd:
#                 intervention/image auto-rotates through exif_read_data(), and
#                 without it phone photos land sideways. Four derived projects
#                 added it.
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
# Alpine dev-package names differ from Debian's: libjpeg-turbo-dev (not
# libjpeg-dev), freetype-dev (not libfreetype6-dev). Same .build-deps +
# scanelf dance as php-base -- see that stage for why the scanelf pass exists.
#
# ca-certificates is NOT installed here: the php:8.5-fpm-alpine base already
# ships it (verified), so outbound HTTPS from PHP works out of the box.
RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        libjpeg-turbo-dev libpng-dev libwebp-dev freetype-dev; \
    docker-php-ext-install pdo_mysql bcmath exif pcntl; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
    docker-php-ext-install gd; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    rm -rf /tmp/pear ~/.pearrc; \
    runDeps="$(scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
        | tr ',' '\n' | sort -u | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }')"; \
    apk add --no-cache $runDeps; \
    apk del --no-network .build-deps

# The CLI opcache file_cache directory. PHP treats a missing or unwritable
# opcache.file_cache as a startup FATAL rather than a warning, which would
# kill every artisan call in the entrypoint before the app booted.
RUN mkdir -p /tmp/opcache && chown www-data:www-data /tmp/opcache

# The official php: images ship NO php.ini, only php.ini-production and
# php.ini-development next to it. Without this copy every directive the app
# ini below does not set falls through to PHP's compiled-in defaults, which
# are the development ones: display_errors=1 (a fatal before Laravel's handler
# exists -- missing vendor, bad bootstrap -- prints paths and a stack trace to
# the visitor), zend.assertions=1 (every assert() in vendor code is compiled
# and run on every request), log_errors=0 (those same early errors go
# nowhere instead of to stderr and the container log). Verified on this
# base. The app ini in conf.d still wins for everything it sets; this only
# changes what it does not.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
# Included by default.conf at the server level AND inside every location that
# sets an add_header of its own (add_header does not merge -- see that file).
# Lives beside nginx.conf rather than in http.d/, which is a glob of SERVER
# blocks: a bare list of add_header directives there is not a valid config and
# the `nginx -t` below would fail the build.
COPY docker/nginx/security-headers.conf /etc/nginx/security-headers.conf
# Role program sets live OUTSIDE conf.d/ deliberately. The distro
# supervisord.conf ends with `[include] files = /etc/supervisor/conf.d/*.conf`,
# so anything dropped in there is loaded by EVERY container -- a worker would
# start nginx and all three roles would run everywhere. The entrypoint copies
# exactly one of these into conf.d/ based on CONTAINER_ROLE.
COPY docker/entrypoint/supervisord.conf /etc/supervisor/roles/app.conf
COPY docker/entrypoint/supervisord.worker.conf /etc/supervisor/roles/worker.conf
COPY docker/entrypoint/supervisord.scheduler.conf /etc/supervisor/roles/scheduler.conf

# Alpine's nginx includes /etc/nginx/http.d/*.conf and has NO sites-available /
# sites-enabled pair; Debian has the sites-* pair and includes that. The vhost
# above is therefore copied into http.d/, not sites-available/.
#
# This one is worse than the supervisor trap because it does not crash. Alpine
# ships its own default server on :80 in http.d/default.conf; copying the app
# vhost to sites-available/ left it never included, so the stock default
# answered every request and the container served 404s while looking perfectly
# healthy to supervisor. Overwriting http.d/default.conf (same filename) is
# what removes it. `nginx -t` here turns a future layout change into a failed
# build rather than a 404 in production.
RUN nginx -t

# Fail the BUILD on a missing extension rather than production.
#
# Every trap this guards against is silent at build time and loud much later:
# a dropped `docker-php-ext-configure gd` flag surfaces on the worker when a
# real photo arrives; a missing pdo_mysql surfaces at the first query; a
# missing redis surfaces only once someone flips CACHE_STORE; a missing pcntl
# never surfaces at all -- the worker just stops honouring SIGTERM and job
# timeouts. gd is checked
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
    php -r 'foreach (["intl", "zip", "pdo_mysql", "bcmath", "exif", "pcntl", "gd", "redis", "Zend OPcache"] as $e) { if (! extension_loaded($e)) { fwrite(STDERR, "FATAL: php extension \"$e\" missing from image\n"); exit(1); } } \
        $i = gd_info(); \
        foreach (["JPEG Support", "PNG Support", "WebP Support", "FreeType Support"] as $f) { if (empty($i[$f])) { fwrite(STDERR, "FATAL: gd built without $f\n"); exit(1); } } \
        if (! ini_get("opcache.enable")) { fwrite(STDERR, "FATAL: opcache present but not enabled -- check docker/php/php.ini reached conf.d\n"); exit(1); } \
        if (ini_get("zend.assertions") !== "-1") { fwrite(STDERR, "FATAL: zend.assertions=" . ini_get("zend.assertions") . " -- php.ini-production was not copied to php.ini, so PHP is on its development defaults\n"); exit(1); } \
        echo "extension check passed: intl zip pdo_mysql bcmath exif pcntl gd(jpeg,png,webp,freetype) redis opcache(enabled) php.ini-production\n";'

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
