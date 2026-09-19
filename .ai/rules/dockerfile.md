---
paths:
  - Dockerfile
  - .github/workflows/docker.yml
  - docker-compose.dokploy.yml
---

# Dockerfile

## The image is built in CI and PULLED by the deploy host — never built there
`.github/workflows/docker.yml` builds the runtime target on every push to main and pushes it to GHCR; `docker-compose.dokploy.yml` has **no `build:` key** and pulls `ghcr.io/$GHCR_REPOSITORY:$IMAGE_TAG`.

That is deliberate, not incidental. Compiling the PHP extensions is ~6 minutes of cc. Doing it on a small VPS that is already serving the app means every deploy competes with production for RAM, and the failure is an OOM. BuildKit reports an OOM as the build *process* dying — there is no `ERROR: process "..." did not complete successfully: exit code N` line — so it reads like a broken Dockerfile and sends you fixing the last step shown, which was never the problem. **Read the log for the shape of the failure: a missing exit-code line means the box died, not the step.**

**Publishing is automatic; deploying is not.** A merge to main builds and pushes — it does not deploy. Nothing in the workflow calls Dokploy, deliberately: nothing reaches production without a person choosing it. The last workflow step writes the `IMAGE_TAG` to paste into Dokploy to the job summary, so deploying is copy, paste, redeploy.

Deploy by an immutable tag (`sha-<full-sha>` or a `v*` release tag), not `latest`. Both are published; `latest` is a convenience pointer, not the deploy target. Two reasons, and the first is the one that bites:

1. With `pull_policy: always`, an **unrelated** container restart — OOM, host reboot, Docker daemon restart — re-pulls the tag. On a moving tag that silently rolls the container forward onto a build nobody chose, and nothing records that it happened. A sha tag restarts as the same bytes every time.
2. Rollback is then just setting `IMAGE_TAG` to an earlier sha and redeploying. `latest` cannot express "the previous one".

`pull_policy: always` is still needed: without it Docker reuses the on-disk copy and a redeploy onto a moved tag changes nothing.

Dokploy needs two things set: `GHCR_REPOSITORY` (owner/repo, lowercase — GHCR rejects uppercase) and a registry credential (a PAT with `read:packages`), because the package is private by default. Without the credential the deploy fails at `docker pull` with "denied" before any container starts.

`docker-compose.yml` (the local stack) still builds from source — that is the verification path, and it is why the Dockerfile must keep working when built locally.

**If CI cannot publish** (exhausted Actions minutes, a broken runner), the fallback is `./docker/publish-image.sh`, which builds the runtime target locally and pushes the same `sha-<full-sha>` tag to the same registry. It is a stand-in for the workflow, not a new deploy path: Dokploy still pulls an immutable tag it did not build, and `IMAGE_TAG` means what it always meant. The rule that survives is **the deploy host does not build the image** — adding a `build:` key to `docker-compose.dokploy.yml` is the wrong fix for this, always. Note that the script must create a `docker-container` buildx builder: Docker's default `docker` driver cannot export a build cache and fails the build outright.

## Official php: images, compiled extensions — not distro packages
Rejected deliberately after evaluation. sury/distro packages would be ~10s instead of ~6min per build, but building in CI already removes that cost from deploys, and the switch would buy a repo that keeps only the latest 8.5.x patch (no pinning an old release) plus a rewritten FPM layout — FPM on a unix socket vs this stack's `fastcgi_pass 127.0.0.1:9000`, per-SAPI conf.d so an ini copied to one SAPI silently misses the other, a `php-fpm8.5` binary name that breaks `command=php-fpm` in supervisord, and an FPM master that logs to a file instead of stderr. Every one of those is a silent failure. Do not switch without a concrete reason.

## The base is Alpine (musl), and that is what makes the image small
272MB on disk / 83MB per pull, down from 757MB / 229MB on bookworm. The saving is almost entirely the base, not anything the app ships, and the reason is worth knowing before anyone proposes going back:

**`php:8.5-fpm-bookworm` keeps the C toolchain permanently** (gcc, g++, cpp, binutils, libc6-dev — ~190MB) in a single 316MB layer, deliberately, so `docker-php-ext-install` and `pecl install` keep working against the image later. `php:8.5-fpm-alpine` puts the same toolchain in a `.build-deps` virtual package and `apk del`s it in the same RUN.

**You cannot claw those 190MB back on Debian by deleting the packages.** A delete in a child layer cannot shrink a parent layer — it writes whiteout entries, so the bytes still ship *and* the delete costs ~1.3MB more. Measured during the migration, not assumed. The only Debian escape is flattening the stage onto `scratch`, which discards layer sharing and every piece of image metadata (`PATH`, `PHP_INI_DIR`, `STOPSIGNAL`…), each of which then has to be re-declared by hand. Alpine avoids the whole problem.

The rest is musl + busybox instead of glibc + GNU coreutils, and no perl (29MB) or python3 (14MB) in the base at all.

**The old "Alpine breaks the JS build" note is dead.** It claimed the esbuild/rolldown optional deps were glibc-only. `vite-plus`, `@tailwindcss/oxide`, `lightningcss`, `oxlint` and `oxfmt` all publish `-musl` builds and all are already resolved in `package-lock.json`. Verified the real thing rather than the manifest: `npm run build` on `node:24-alpine` emits assets whose md5sums are identical to the Debian build, file for file.

### What musl costs
- Different allocator and much smaller default thread stacks. Nothing here tripped on it, but this is the classic source of "fine on Debian, segfaults in production" for PHP extensions. Exposure is low while this stays pure PHP plus the standard extensions; it rises the moment an exotic PECL extension is added.
- ICU 78.1 vs bookworm's 72.1. Currency, date, collation and transliteration output was diffed across both and is identical — but an ICU major *can* change collation ordering. Re-check if user-facing lists are ever sorted through `Collator`.
- `bash` is installed explicitly (~1MB): the base has busybox `ash` only, and `entrypoint.sh` is `#!/usr/bin/env bash`.

## Alpine and Debian disagree on nginx and supervisor paths — both traps are guarded
Both of these bit during the migration. Neither is hypothetical, and neither announces itself.

**supervisor.** Alpine uses `/etc/supervisord.conf` with `[include] files = /etc/supervisor.d/*.ini`; Debian uses `/etc/supervisor/supervisord.conf` with `conf.d/*.conf`. The entrypoint, the `CMD` and all three role files speak the Debian layout, so the Dockerfile *recreates* that layout with a `sed` rather than forking them. The `grep -q` after the sed is load-bearing: if an Alpine update changes that `[include]` line the sed matches nothing, and every role would then start with **no programs** — a container that boots, stays healthy to Docker, and runs nothing. Fail the build instead.

**nginx.** Alpine includes `/etc/nginx/http.d/*.conf` and has no `sites-available`/`sites-enabled` pair, so the vhost is copied to `http.d/default.conf` — the same filename as Alpine's stock default server, which is what removes it. Copying it to `sites-available/` instead leaves it never included, and Alpine's default server answers on :80: **the container serves 404s while looking perfectly healthy to supervisor.** `RUN nginx -t` is the guard that turns a future layout change into a failed build.

Both apt-era cleanups were replaced by the `.build-deps` + `scanelf` dance. The `scanelf` pass re-adds, as real runtime deps, the shared libraries the freshly built `.so` files link against (`so:libicuuc.so.78` and friends) *before* `apk del .build-deps` runs. Without it the del takes icu's libraries with it and intl fails to load.

## Base images are pinned by DIGEST
`php:8.5-fpm-alpine@sha256:...` and `node:24-alpine@sha256:...`, not bare tags. A floating tag republished upstream silently invalidates the cache and re-runs the whole extension compile, and changes the runtime out from under a rebuild of an old commit. Dependabot's `docker` ecosystem bumps these monthly (`.github/dependabot.yml`).

When bumping a digest, update the `@ PHP x.y.z` / `@ Node vX` comment above the `FROM` too — it is the only place the human-readable version is recorded.

## The build asserts its own extensions — keep it that way
The runtime stage ends with a `php -r` assertion over intl, zip, pdo_mysql, bcmath, exif, pcntl, gd (per format), redis and opcache. It exists because **every** extension trap in this image is silent at build time and loud much later: a dropped `docker-php-ext-configure gd --with-jpeg` flag surfaces on the worker when a real photo arrives; a missing pdo_mysql at the first query; a missing redis only once someone flips `CACHE_STORE`. Verified to genuinely fail (exit 1) against a bare base image — it is not a guard that only ever passes.

Two traps encoded in that line, both hit while writing it:

- **OPcache must be spelled `"Zend OPcache"`.** It is a Zend extension: `extension_loaded("opcache")` is **false** and `php -m | grep -ix opcache` finds nothing, on an image where OPcache is loaded and enabled (`php -v` shows it, `opcache_get_status()` exists). Lowercasing that string turns the check into a guard that always fails.
- **Use `extension_loaded()`, not `php -m | grep`.** Same reason, and it avoids matching substrings of unrelated module names.

The assertion sits **after** the `COPY docker/php/php.ini` line so it also validates the shipped config — it asserts `opcache.enable`, so an ini that never reached conf.d fails the build rather than quietly serving every request uncompiled. Moving it earlier silently weakens it.

Do not verify this by hand any more; the build does it. If you do need a manual check, the ENTRYPOINT intercepts commands and exits on a missing APP_KEY, so `--entrypoint php` is required:

  docker run --rm --entrypoint php <image> -r 'var_dump(gd_info());'

## A shared `php-base` stage owns the extension set
`vendor` and `runtime` both derive from `php-base` (intl, zip), so the shared set is written once. This is load-bearing for composer, not tidiness: the vendor stage's `composer install` validates composer.lock's platform requirements against the extensions in **its own** image, so if that set drifts below production's, the lock is being validated against something production does not run. `composer check-platform-reqs --no-dev` confirms intl and zip are the only hard non-bundled requirements; runtime adds pdo_mysql, bcmath, exif, pcntl, gd and redis on top.

## Runtime-only extension notes
- **pdo_mysql, not pdo_sqlite.** Both compose files set `DB_CONNECTION: mariadb`; sqlite is a local-development default only. If you ever do need pdo_sqlite, `docker-php-ext-install pdo_sqlite` fails at configure time ("Package 'sqlite3' ... not found") unless `sqlite-dev` is added to `.build-deps` — the base ships no sqlite headers. Check any other `pdo_*` driver the same way; pdo_mysql happens not to need one.
- **gd needs `docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype`** before install (and `libjpeg-turbo-dev libpng-dev libwebp-dev freetype-dev` in `.build-deps` — note Alpine's names differ from Debian's `libjpeg-dev` / `libfreetype6-dev`). intervention/image decodes user uploads through it.
- **pcntl is what makes the worker's shutdown contract real, and its absence is silent.** `queue:work` registers its SIGTERM handler and the per-job `$timeout` alarm through `pcntl_signal`/`pcntl_alarm`, and Laravel skips both without logging anything when the extension is missing. Without it, supervisord's `stopwaitsecs` buys nothing — the worker dies mid-job on the first SIGTERM of every deploy — and `App\Jobs\Job::$timeout` is never enforced. Bundled, no system deps; the assertion is the only thing that would ever notice it gone.
- **bcmath and exif are there because derived projects kept adding them.** Five of the eight apps built from this boilerplate added bcmath (decimal money/quantity arithmetic) and four added exif (upload orientation — intervention/image auto-rotates through `exif_read_data()`, and without it phone photos land sideways). Both are bundled with no system deps, so the cost of shipping them unused is nil and the cost of discovering they are missing is a production incident.
- **redis comes from PECL** (`pecl install redis && docker-php-ext-enable redis`) — `docker-php-ext-install` only knows bundled extensions. It is installed although every driver defaults to `database`, so moving to Redis is an env flip and a redeploy, not an image rebuild. ~2MB, and nothing loads it while the drivers stay on database.

## php.ini-production is copied into place — the base ships no php.ini at all
The official `php:` images provide `php.ini-production` and `php.ini-development` but no `php.ini`, so without the `cp` in the runtime stage every directive `docker/php/php.ini` does not set falls through to PHP's compiled-in defaults — and those are the development ones: `display_errors=1`, `zend.assertions=1`, `log_errors=0`. Verified on the pinned base. Laravel masks most of it once booted, but a fatal *before* its handler exists (missing vendor, bad bootstrap) prints paths and a stack trace to the visitor, and `assert()` calls in vendor code are compiled and run on every request. The app ini in `conf.d/` still wins for everything it sets. The build asserts `zend.assertions == -1`, so removing the `cp` fails the build rather than silently reverting to development defaults.

## ca-certificates is already present — do not add it
`php:8.5-fpm-alpine` ships ca-certificates (verified), so outbound HTTPS from PHP works out of the box. The "minimal images have no CA bundle" trap is real but does not apply to this base.

## Comments between `\` continuations are fine
The `#` lines sitting between `\` continuations in the RUN blocks are stripped by Docker before the shell sees them (verified with a probe build). Do not "fix" them by moving them out.
