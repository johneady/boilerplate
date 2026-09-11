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

Log in at `/login` as `admin@example.com` / `password` (see "The seeded demo
accounts" below). Locally the login page also shows one-click buttons for both
seeded accounts. Seeding is idempotent, so redeploys will not reset a password
you have since changed.

## Deploy on Dokploy

1. Create a MariaDB deployment in Dokploy; note the internal host, database,
   user and password.
2. Create a Compose application from this repo, compose path
   `docker-compose.dokploy.yml`.
3. Set the application's environment. Paste the whole block below into
   Dokploy's environment editor (bulk mode), then replace every `FILL_ME_IN` —
   each one is a single double-click to select.

   ```dotenv
   # ---- required: the deploy fails with a named error if any is missing ----
   # php artisan key:generate --show  (reuse across redeploys: sessions are
   # encrypted, so changing it logs everyone out)
   APP_KEY=FILL_ME_IN
   # The https:// domain Traefik serves, e.g. https://app.example.com
   APP_URL=FILL_ME_IN
   # The Dokploy-managed MariaDB, NOT localhost
   DB_HOST=FILL_ME_IN
   DB_DATABASE=FILL_ME_IN
   DB_USERNAME=FILL_ME_IN
   DB_PASSWORD=FILL_ME_IN

   # ---- optional: delete any line you do not want to change ----
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

   There is no admin account to configure: the seeder creates a fixed demo
   admin, covered below.

4. Deploy. The entrypoint waits for the database, migrates, seeds the demo
   accounts, caches config/routes, publishes Filament's assets, and starts the
   web, worker and scheduler roles.

5. **Change the admin password.** The seeded credentials are public (below).

### The seeded demo accounts

Both are created on first boot and are the same on every deployment, so there is
nothing to set and nothing to forget:

| Account | Email | Password | Access |
| --- | --- | --- | --- |
| Admin | `admin@example.com` | `password` | Filament panel at `/admin` |
| User | `test@example.com` | `password` | `/dashboard` only |

> **These credentials are public.** Anyone who knows this boilerplate can sign in
> to a fresh instance. Change the admin password from its settings page as soon
> as the instance is reachable — re-seeding never resets a changed password, so
> the change survives every redeploy.
>
> The non-admin demo user is seeded in **every environment except production**,
> matching where the one-click quick-login buttons appear on the login page. A
> non-production instance therefore offers **passwordless** login to both
> accounts — deploy anything real as `APP_ENV=production` (the default).

### Gotchas

- **`required variable DOCKER_LOCAL_STACK is missing a value`**: you left off
  `--env-file .env.docker`. That is the guard working — see "Verify locally".
  Copy `.env.docker.example` to `.env.docker` if you have not already.
- **`$` in secrets**: Compose interpolates `$` in environment values
  (`Pas$W0rd!` arrives as `Pas!`). Escape as `$$` in Dokploy's editor and
  confirm with `docker compose exec -T app printenv DB_PASSWORD`.
- **Resetting a demo instance**: `migrate:fresh` and `db:wipe` are PROHIBITED
  outside `local`/`testing` (`DB::prohibitDestructiveCommands()` in
  AppServiceProvider), so they fail in the Dokploy console by design — the
  guard is there so a deployed database cannot be dropped by a stray command.
  Re-seeding does not reset anything either: `RUN_SEEDERS` only fills in what
  is missing. To genuinely start over, delete the managed database's data (or
  the database itself) in Dokploy and redeploy.
- **Health**: the container healthcheck hits `/up`. If the app is marked
  unhealthy, check `docker compose logs app` — the most common cause is a
  wrong `DB_*` value. The worker and scheduler serve no HTTP, so they override
  that check and ask supervisor whether their own process is running instead.
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
