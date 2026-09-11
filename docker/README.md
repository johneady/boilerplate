# Docker & Dokploy deployment

The app ships as a single image built from a three-stage `Dockerfile` (composer
vendor → vite-plus assets → runtime), backed by a managed MariaDB. That one
image runs three **roles**, selected per container by `CONTAINER_ROLE`:

| Role | `CONTAINER_ROLE` | Runs | Scale to |
| --- | --- | --- | --- |
| web | `app` (default) | nginx + php-fpm | as many as you like |
| queue worker | `worker` | `queue:work` | as many as you like |
| scheduler | `scheduler` | `schedule:work` | **exactly one** |

Roles are separate containers because they fail and scale independently: a
worker wedged on a slow job must not stop requests being served, and restarting
the web tier must not kill a job mid-flight. Only the **web** role runs
migrations and seeding — worker and scheduler containers force both off and
wait for the schema before starting.

The scheduler must stay at one replica: instances do not divide work between
them, they each fire every due task.

- `Dockerfile` — the container image
- `docker-compose.yml` — **local verification stack** (embedded throwaway key,
  known password, published port — never deploy this one)
- `docker-compose.dokploy.yml` — the **Dokploy deployment stack** (no database
  service, no ports, every secret supplied by Dokploy)
- `docker/nginx`, `docker/php`, `docker/entrypoint` — runtime configs
  (`supervisord.conf`, `supervisord.worker.conf`, `supervisord.scheduler.conf`
  are the per-role program sets; the entrypoint installs exactly one)

## Verify locally

**Always pass `--env-file .env.docker`.** Compose's default variable file is
the project-root `.env` — the application's *own* Laravel env — so a bare
`docker compose up` silently interpolates your development values (`APP_PORT`,
`APP_URL`, anything sharing a name) into this throwaway stack. The stack
declares a `DOCKER_LOCAL_STACK` tripwire with no default, so forgetting the
flag fails with a named error instead of booting on the wrong values.

```bash
cp .env.docker.example .env.docker   # first time only; .env.docker is gitignored
docker compose --env-file .env.docker up --build -d
docker compose --env-file .env.docker logs -f app        # migrations + seeding
docker compose --env-file .env.docker logs -f worker     # jobs being processed
docker compose --env-file .env.docker logs -f scheduler  # one tick per minute
open http://localhost:8011                               # or APP_PORT
```

All three roles come up together. Check each is running only its own processes:

```bash
docker compose --env-file .env.docker ps    # app, worker, scheduler, mysql
docker compose --env-file .env.docker exec app php artisan queue:failed
docker compose --env-file .env.docker exec app php artisan schedule:list
```

Log in at `/login`. The entrypoint migrates and seeds on boot; the admin user
comes from `FIRST_USER_EMAIL` / `FIRST_USER_PASSWORD` (see the local stack's
values in `docker-compose.yml`). Seeding is idempotent, so redeploys will not
reset a password you have since changed.

## Deploy on Dokploy

1. Create a MariaDB deployment in Dokploy; note the internal host, database,
   user and password.
2. Create a Compose application from this repo, compose path
   `docker-compose.dokploy.yml`.
3. Set the application's environment. Paste this into Dokploy's environment
   editor (bulk mode) and fill in the `<>` placeholders:

   ```dotenv
   # REQUIRED — deploy fails with a named error if any of these is missing
   APP_KEY=
   APP_URL=https://<your-domain>
   FIRST_USER_EMAIL=<your-admin-email>
   FIRST_USER_PASSWORD=<a-strong-password>
   DB_HOST=<managed-mariadb-host>
   DB_DATABASE=<database>
   DB_USERNAME=<user>
   DB_PASSWORD=<password>
   ```

   Generate `APP_KEY` once with `php artisan key:generate --show` and reuse it
   across redeploys — sessions are encrypted (`SESSION_ENCRYPT=true`), so a
   changed key logs everyone out.

   Optional overrides (sensible defaults are baked into the compose file, so
   only paste the ones you actually want to change):

   ```dotenv
   # Optional
   APP_NAME=Boilerplate
   LOG_LEVEL=warning
   DB_CONNECTION=mariadb
   DB_PORT=3306
   RUN_SEEDERS=true
   MAIL_MAILER=log
   MAIL_FROM_ADDRESS=hello@example.com
   MAIL_FROM_NAME=Boilerplate
   ```

   Everything else (`APP_ENV`, `APP_DEBUG`, `TRUST_PROXIES`,
   session/cache/queue drivers, …) is hardcoded in `docker-compose.dokploy.yml`
   and must not be set here.

4. Deploy. The entrypoint waits for the database, migrates, seeds the demo
   catalogue on the first boot only, caches config/routes, publishes Filament's
   assets and starts nginx + php-fpm behind Traefik.

### Gotchas

- **`required variable DOCKER_LOCAL_STACK is missing a value`**: you left off
  `--env-file .env.docker`. That is the guard working — see "Verify locally".
  Copy `.env.docker.example` to `.env.docker` if you have not already.
- **`$` in secrets**: Compose interpolates `$` in environment values
  (`Pas$W0rd!` arrives as `Pas!`). Escape as `$$` in Dokploy's editor and
  confirm with `docker compose exec -T app printenv DB_PASSWORD`.
- **Resetting the demo** (wipe orders/inquiries created by prospects, keep
  schema): run `php artisan migrate:fresh --seed --force` in the Dokploy
  console. `RUN_SEEDERS` only seeds an EMPTY database; it never resets one.
- **Health**: the container healthcheck hits `/up`. If the app is marked
  unhealthy, check `docker compose logs app` — the most common cause is a
  wrong `DB_*` value. The worker and scheduler have **no** healthcheck on
  purpose: they serve no HTTP, so `/up` would always fail and the platform
  would restart perfectly healthy containers.
- **Jobs are queued but nothing happens**: the worker container is down, or
  `QUEUE_CONNECTION` is not `database`. Check `docker compose logs worker` and
  `php artisan queue:failed`. Locally, outside Docker, `composer run dev`
  already runs a worker (`queue:listen`); without it, run
  `php artisan queue:work` yourself.
- **Scheduled tasks run twice**: more than one scheduler container. Scale that
  service back to one; `onOneServer()` on each task is a backstop, not a
  licence to scale it.
- **A scheduled task stops running**: a task killed mid-run leaves its
  `withoutOverlapping()` lock behind until it expires. Clear it with
  `php artisan schedule:clear-cache`.
