# Docker & Dokploy deployment

The app ships as a single container (nginx + php-fpm + supervisor) built from a
three-stage `Dockerfile` (composer vendor → vite-plus assets → runtime), backed
by a managed MariaDB.

- `Dockerfile` — the container image
- `docker-compose.yml` — **local verification stack** (embedded throwaway key,
  known password, published port — never deploy this one)
- `docker-compose.dokploy.yml` — the **Dokploy deployment stack** (no database
  service, no ports, every secret supplied by Dokploy)
- `docker/nginx`, `docker/php`, `docker/entrypoint` — runtime configs

## Verify locally

```bash
docker compose up --build -d
docker compose logs -f app        # watch migrations + first-boot seeding
open http://localhost:8011
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

- **`$` in secrets**: Compose interpolates `$` in environment values
  (`Pas$W0rd!` arrives as `Pas!`). Escape as `$$` in Dokploy's editor and
  confirm with `docker compose exec -T app printenv DB_PASSWORD`.
- **Resetting the demo** (wipe orders/inquiries created by prospects, keep
  schema): run `php artisan migrate:fresh --seed --force` in the Dokploy
  console. `RUN_SEEDERS` only seeds an EMPTY database; it never resets one.
- **Health**: the container healthcheck hits `/up`. If the app is marked
  unhealthy, check `docker compose logs app` — the most common cause is a
  wrong `DB_*` value.
