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

## Official php: images, compiled extensions — not Debian/sury
Rejected deliberately after evaluation. sury would be ~10s instead of ~6min per build, but building in CI already removes that cost from deploys, and the switch would buy: a repo that keeps only the latest 8.5.x patch (no pinning an old release), plus the entire Debian layout — FPM on a unix socket vs this stack's `fastcgi_pass 127.0.0.1:9000`, separate `/etc/php/8.5/{fpm,cli}/conf.d` so an ini copied to one SAPI silently misses the other, a `php-fpm8.5` binary name that breaks `command=php-fpm` in supervisord, and an FPM master that logs to a file instead of stderr. Every one of those is a silent failure. Do not switch without a concrete reason.

## Base images are pinned by DIGEST
`php:8.5-fpm-bookworm@sha256:...` and `node:24-bookworm-slim@sha256:...`, not bare tags. A floating tag republished upstream silently invalidates the cache and re-runs the whole extension compile, and changes the runtime out from under a rebuild of an old commit. Dependabot's `docker` ecosystem bumps these monthly (`.github/dependabot.yml`).

When bumping a digest, update the `@ PHP x.y.z` / `@ Node vX` comment above the `FROM` too — it is the only place the human-readable version is recorded.

## The build asserts its own extensions — keep it that way
The runtime stage ends with a `php -r` assertion over intl, zip, pdo_mysql, gd (per format), redis and opcache. It exists because **every** extension trap in this image is silent at build time and loud much later: a dropped `docker-php-ext-configure gd --with-jpeg` flag surfaces on the worker when a real photo arrives; a missing pdo_mysql at the first query; a missing redis only once someone flips `CACHE_STORE`. Verified to genuinely fail (exit 1) against a bare base image — it is not a guard that only ever passes.

Two traps encoded in that line, both hit while writing it:

- **OPcache must be spelled `"Zend OPcache"`.** It is a Zend extension: `extension_loaded("opcache")` is **false** and `php -m | grep -ix opcache` finds nothing, on an image where OPcache is loaded and enabled (`php -v` shows it, `opcache_get_status()` exists). Lowercasing that string turns the check into a guard that always fails.
- **Use `extension_loaded()`, not `php -m | grep`.** Same reason, and it avoids matching substrings of unrelated module names.

The assertion sits **after** the `COPY docker/php/php.ini` line so it also validates the shipped config — it asserts `opcache.enable`, so an ini that never reached conf.d fails the build rather than quietly serving every request uncompiled. Moving it earlier silently weakens it.

Do not verify this by hand any more; the build does it. If you do need a manual check, the ENTRYPOINT intercepts commands and exits on a missing APP_KEY, so `--entrypoint php` is required:

  docker run --rm --entrypoint php <image> -r 'var_dump(gd_info());'

## A shared `php-base` stage owns the extension set
`vendor` and `runtime` both derive from `php-base` (intl, zip), so the shared set is written once. This is load-bearing for composer, not tidiness: the vendor stage's `composer install` validates composer.lock's platform requirements against the extensions in **its own** image, so if that set drifts below production's, the lock is being validated against something production does not run. `composer check-platform-reqs --no-dev` confirms intl and zip are the only hard non-bundled requirements; runtime adds pdo_mysql, gd and redis on top.

## Runtime-only extension notes
- **pdo_mysql, not pdo_sqlite.** Both compose files set `DB_CONNECTION: mariadb`; sqlite is a local-development default only. If you ever do need pdo_sqlite, `docker-php-ext-install pdo_sqlite` fails at configure time ("Package 'sqlite3' ... not found") unless `libsqlite3-dev` is on the same apt line — the base ships no sqlite headers. Check any other `pdo_*` driver the same way; pdo_mysql happens not to need one.
- **gd needs `docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype`** before install (and libjpeg-dev, libpng-dev, libwebp-dev, libfreetype6-dev on the apt line). intervention/image decodes user uploads through it.
- **redis comes from PECL** (`pecl install redis && docker-php-ext-enable redis`) — `docker-php-ext-install` only knows bundled extensions. It is installed although every driver defaults to `database`, so moving to Redis is an env flip and a redeploy, not an image rebuild. ~2MB, and nothing loads it while the drivers stay on database.

## ca-certificates is already present — do not add it
`php:8.5-fpm-bookworm` ships ca-certificates (verified). The "slim images have no CA bundle, so outbound HTTPS from PHP fails" trap is real but applies to `debian:*-slim`, which this image is not based on. Only revisit if the base is ever rebased onto a slim variant.

## `DEBIAN_FRONTEND=noninteractive` is set per-RUN, never as ENV
As an `ENV` it persists into the final image and into every `docker exec`, where a later interactive apt then silently skips prompts it should have asked.

## Comments between `\` continuations are fine
The `#` lines sitting between `\` continuations in the RUN blocks are stripped by Docker before the shell sees them (verified with a probe build). Do not "fix" them by moving them out.
