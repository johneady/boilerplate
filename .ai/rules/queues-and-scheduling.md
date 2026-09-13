---
paths:
  - 'app/Jobs/**'
  - 'app/Console/Commands/**'
  - routes/console.php
  - docker/entrypoint/supervisord.conf
  - docker/entrypoint/supervisord.worker.conf
  - docker/entrypoint/supervisord.scheduler.conf
  - docker/entrypoint/entrypoint.sh
  - docker-compose.yml
  - docker-compose.dokploy.yml
  - app/Jobs/ProcessUploadedImage.php
  - .env.production.example
---

# Queues & scheduling

## One image, three roles, selected by CONTAINER_ROLE
The same image runs the web tier (`app`), a queue worker (`worker`) and the scheduler (`scheduler`). The entrypoint reads `CONTAINER_ROLE` and installs exactly one supervisor program set; every compose service is otherwise identical, so a role cannot be started with another role's processes.

Role program sets live in `/etc/supervisor/roles/`, **not** `conf.d/`. The distro `supervisord.conf` ends with `[include] files = /etc/supervisor/conf.d/*.conf`, so anything left in `conf.d/` is loaded by every container — put a worker config there and the web container starts a worker too, and vice versa. The entrypoint clears `conf.d/` and copies in the one file for the active role.

Adding a role means: a new `docker/entrypoint/supervisord.<role>.conf`, a `COPY` into `/etc/supervisor/roles/<role>.conf`, the name added to the entrypoint's `case` validation, and a compose service. The entrypoint fails loudly on an unknown role rather than starting nothing.

## Only the web role touches the database schema
`RUN_MIGRATIONS` and `RUN_SEEDERS` are forced off for `worker` and `scheduler` in the entrypoint, whatever the environment sets. Two containers starting together would otherwise race the same migration, and `migrate --isolated` takes its lock through the cache table that the first migration is still creating.

Non-web roles instead wait for the `jobs` table to exist before starting (`WAIT_FOR_MIGRATIONS`, 5 minute ceiling). Without that wait a worker boots against a schema that does not exist yet and crashloops past Docker's restart backoff on a slow first deploy.

## Exactly one scheduler, any number of workers
Scheduler instances do not divide work between them — each fires every due task. Keep that service at one replica. Workers coordinate through the database, so scaling them is safe: raise `numprocs` or run more worker containers (prefer the latter, which survives a host loss).

`schedule:work` is used rather than a crontab, because it is a long-running process and needs no cron daemon in the image.

## Every scheduled task needs withoutOverlapping() and onOneServer()
Neither guard fails visibly in development; the symptom appears under load or after scaling. `withoutOverlapping()` must be given an explicit expiry — its default is 24 hours, and a task killed with SIGKILL never releases its lock, so an hourly task would stay blocked for a day after one hard restart. `onOneServer()` needs a lock-capable cache store (`database` qualifies, `file` and `array` do not).

`tests/Feature/ScheduledTasksTest.php` asserts both on every maintenance task, so a task added without them fails the suite.

## The database session and cache tables prune nothing by themselves
Both treat expiry as a read-time check: an expired row is ignored, never deleted. Laravel ships no pruning for the database cache store, and session GC is left to PHP's probabilistic handler, which never fires because Laravel drives sessions itself. `app:prune-expired-storage` (hourly) does both. Laravel's own `cache:prune-stale-tags` is **Redis-only** and is a no-op against this stack — do not schedule it instead.

## Job timeout must stay below the connection's retry_after
A job still running when `retry_after` (90s, `config/queue.php`) elapses is handed to a second worker while the first is mid-flight, and runs twice. `App\Jobs\Job::$timeout` is 60s for that reason, and supervisor's `stopwaitsecs` (90s) exceeds it so a deploy does not kill a job mid-work. Changing any one of those three means checking the other two; a test asserts the timeout/retry_after relationship.

## Extend App\Jobs\Job, not ShouldQueue directly
`make:job` emits a standalone class. Extending `App\Jobs\Job` instead gives bounded retries, exponential backoff, `deleteWhenMissingModels`, and a failure hook that logs — a `failed_jobs` row alone is easy to never look at. Override any of it per job; the values are defaults.

Dispatching needs a running worker. `composer run dev` starts one for you — Laravel 13's `DevCommands::registerDefaults()` registers `queue:listen --tries=1 --timeout=0` unconditionally, alongside `serve`, `pail` and `vite`. Outside that script, run `php artisan queue:work` yourself or dispatched jobs simply sit in the `jobs` table.

Note `queue:listen` (development) reboots the framework per job so new code is picked up without a restart; the container uses `queue:work` (long-running), which does not.

## `app` must be the LAST service in docker-compose.dokploy.yml
Dokploy injects the Traefik routing labels for the application's domain by appending a `labels:` block to the **last** service in the file. If `worker` or `scheduler` is last, those labels land on a container running no web server: Traefik routes the domain at nothing listening on port 80 and every request returns 502 while all containers report healthy. Add new non-web services above `app`, never below it.

(Learned in ~/php/pet-adoption, which hit this in production.)

## Non-web roles need a healthcheck that can actually fail
The image `HEALTHCHECK` curls nginx, which worker and scheduler do not run, so without an override they report unhealthy while working perfectly — and the platform restarts them in a loop.

Do **not** check with a glob over `/proc/*/cmdline`: the healthcheck itself runs as `sh -c "... queue:work ..."`, so its own command line matches and the check can never fail. Verified: a `grep -l schedule:work /proc/[0-9]*/cmdline` inside the *worker* container returns success.

Because supervisor is PID 1 in these containers (the entrypoint execs supervisord, not the role process), ask supervisor for the managed process state instead: `supervisorctl status queue-worker | grep -q RUNNING`. That returns non-zero once the process dies or backs off — confirmed by stopping it.

## The local stack must be run with --env-file .env.docker
Compose's default variable file is the project-root `.env` — which in a Laravel repo is the *application's own* env. A bare `docker compose up` therefore interpolates development values (`APP_PORT`, `APP_URL`, anything sharing a name) into the throwaway local stack with no warning; verified by adding `APP_PORT=9999` to `.env` and watching the published port change.

`docker-compose.yml` declares `DOCKER_LOCAL_STACK` with `${...:?}` and no default, and it is set only in the gitignored `.env.docker`. Forgetting the flag then fails with a named error rather than booting on the wrong values. The variable is otherwise unused — the failure is the point, so do not give it a default or move it into the compose file.

`.env.docker.example` is the tracked template; `.env.docker` is gitignored.

(Borrowed from ~/php/pet-adoption.)

## Avatar removal cancels an in-flight processing job
Deleting an avatar cannot un-queue a ProcessUploadedImage already dispatched, so Profile::deleteAvatar() stamps a cache marker (ProcessUploadedImage::removalKey($userId) => time()) and the job compares it against its own $dispatchedAt. If the removal is newer, the job deletes the conversions it just wrote and skips attaching — otherwise a Remove clicked seconds after an upload is silently undone when the worker catches up. Keep the $dispatchedAt constructor default (time()) if you add dispatch call sites.

deleteAvatar() records the marker even when avatar_path is already null. That is the whole point: during an in-flight first upload avatar_path IS null, so an early return there would skip the marker in exactly the case the guard exists for. A test asserts the job's result is discarded end-to-end, not merely that the marker was written.

The marker lives in the cache, so it depends on CACHE_STORE being shared across processes. `database` (this project's default) and Redis both qualify; a per-process store such as `array` or `file` alongside a real worker would silently break the guard. If that ever changes, move the marker to a column.

The job also deletes the staged source BEFORE pruning the previous avatar directory. Ordering matters: once the source is gone a retry early-returns, so pruning last means a retry can never destroy the old set after the new one was rolled back.

The `local` disk sets `throw => false`, so Storage::get() returns null (not an exception) on an unreadable file. The job null-checks before decodeBinary(); without it you get a TypeError outside the try/catch, bypassing the rollback.

## app:check-production audits loaded config, and names the safe environments
`.env.production.example` is the production-shaped companion to `.env.example`, which is deliberately local-shaped (sqlite, APP_DEBUG=true, MAIL_MAILER=log) because that is what a fresh clone wants. Copying the local one to a server and editing what looks wrong is how an instance ends up with debug output on a public page or a mailer writing customer mail to a log file. Keep the two in sync when adding an env var, and keep the production file's defaults pointing the safe way.

`app:check-production` is the half an example file cannot cover: it audits the configuration actually loaded, catching a stale .env carried over from a previous deploy or platform-injected vars. Every check reads config(), never env() — after `config:cache`, env() returns null for anything outside the cached file, so an env()-based check reports a correct instance as broken.

The environment guard names the safe environments (`local`, `testing`) rather than excluding production, because APP_ENV is overridable on the Dokploy deploy and a staging instance owns a real database. Rewriting it as `! isProduction()` makes 12 tests fail, which is the point. Same reasoning as DB::prohibitDestructiveCommands(); see .ai/rules/dev-login.md.

Failures (exit non-zero) are unsafe in every deployment: APP_DEBUG, empty APP_KEY, a non-delivering MAIL_MAILER, empty from address, array cache. Warnings are supported choices with a real cost and exit zero unless --warnings-as-errors is passed — making them always fatal breaks the command as a deploy gate, since an installation that accepted the cost could never pass.

Note `fail()` and `warn()` cannot be private methods on a Command subclass: both collide with Illuminate\Console\Command's own public methods and fatal at autoload time. They are `recordFailure()`/`recordWarning()` here.

## Redis is wired but commented out; database stays the default
Both compose files ship Redis ready to switch on, not switched on. docker-compose.yml carries a commented-out `redis` service (redis:8-alpine, `--appendonly yes`, its own volume and a redis-cli ping healthcheck) plus commented depends_on and REDIS_* entries; docker-compose.dokploy.yml instead parameterises CACHE_STORE/QUEUE_CONNECTION/SESSION_DRIVER and the REDIS_* vars, because Dokploy provisions Redis as a managed service on dokploy-network the same way it provisions MariaDB. Self-hosting it there means adding the service ABOVE `app` — never below, for the Traefik-label reason documented in queues-and-scheduling.md.

`--appendonly yes` and a persistent volume are not optional in that commented block: a Redis that loses its data on restart logs every user out when SESSION_DRIVER=redis.

app:prune-expired-storage stays scheduled after any switch. It exists because the database cache and session stores never delete their own expired rows; Redis expires keys itself, and the command already skips any store that is not `database` (verified: `CACHE_STORE=redis SESSION_DRIVER=redis php artisan app:prune-expired-storage` prints "skipping" for both). A mixed setup — Redis cache, database queue — is normal, so do not remove it.

The phpredis extension is already in the image (see .ai/rules/dockerfile.md), so switching a driver is an env change plus a running Redis, never an image rebuild.
