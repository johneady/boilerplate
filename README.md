# Boilerplate

A Laravel 13 starting point with authentication, a Livewire + Flux UI frontend, and a
test suite wired for Test Impact Analysis.

## Stack

| Layer     | Choice                                                       |
| --------- | ------------------------------------------------------------ |
| Runtime   | PHP 8.5, Laravel 13                                          |
| Frontend  | Livewire 4, Flux UI 2, Tailwind CSS 4, Vite 8 (`vite-plus`)  |
| Auth      | Laravel Fortify (single login page)                           |
| Admin     | Filament 5 at `/admin`                                       |
| Testing   | Pest 5 with the Tia engine                                   |
| Static    | Larastan / PHPStan                                           |
| Style     | Laravel Pint                                                 |
| AI tools  | Laravel Boost, `.ai/rules`                                   |
| Deploy    | Docker (nginx + php-fpm + supervisor), Dokploy-ready          |

## Getting started

```bash
composer setup
```

That installs dependencies, creates `.env`, generates an app key, migrates, and
builds assets. Then run the dev server:

```bash
composer dev
```

## What's included

### Authentication

Fortify provides the backend; the UI is Livewire components under
`app/Livewire/Settings`. Enabled features in `config/fortify.php`:

- Registration and login
- Password reset and email verification
- Two-factor authentication (TOTP), with password confirmation
- Passkeys / WebAuthn, via Fortify's passkey support (`@laravel/passkeys`)

Fortify actions live in `app/Actions/Fortify`, with shared validation rules
extracted into `app/Concerns` (`PasswordValidationRules`,
`ProfileValidationRules`).

### Authorization

Roles and permissions are defined in code, not in database rows:
[`App\Auth\Role`](app/Auth/Role.php) and
[`App\Auth\Permission`](app/Auth/Permission.php). A user's `role` column is the
single source of truth; [`HasRoles`](app/Concerns/HasRoles.php) supplies
`hasRole()`, `hasPermission()`, `hasRoleAtLeast()` and a `withRole` scope.

```php
$user->hasPermission(Permission::ManageSettings);
$user->can(Permission::ManageSettings->value);   // through the Gate
$user->can('update', $otherUser);                // through UserPolicy
User::withRole(Role::Admin)->get();
```

Check permissions rather than role names. `Role::Admin->permissions()` returns
`Permission::cases()` by enumeration, so a permission added later is granted to
administrators rather than silently denied.

Policies extend [`BasePolicy`](app/Policies/BasePolicy.php), which maps each of
the seven standard abilities to a permission and **denies any ability it has no
mapping for** — a new ability, or one somebody forgot to map, refuses rather
than allows.

[`AuthServiceProvider`](app/Providers/AuthServiceProvider.php) registers every
permission as a Gate ability and adds a `Gate::before` administrator bypass, so
a model whose policy has not been written yet is still manageable. The bypass
deliberately **skips** `delete`, `forceDelete` and `updateRole`: those are the
rules that deny an administrator on purpose — you cannot delete your own account
or demote yourself, which on a single-admin instance would lock the panel out of
itself. `Gate::before` short-circuits on any non-null return, so answering them
there would silently undo [`UserPolicy`](app/Policies/UserPolicy.php).

`$user->is_admin` still works: it is an accessor derived from the role, kept so
existing call sites did not have to change. New code should ask for a permission.

Adding a role is a case in the enum plus its grants in `permissions()` — no
migration, because the column is a string rather than a native enum (adding a
value to a MySQL/MariaDB enum column rewrites the table).

### Admin panel

Filament 5 serves an admin panel at `/admin`. Access is gated on the
`AccessAdminPanel` permission in two places: `User::canAccessPanel()` (Filament's
`FilamentUser` contract) and
[`FilamentAuthenticate`](app/Http/Middleware/FilamentAuthenticate.php), which
403s non-admins and sends guests to Fortify's login page.

**The panel has no login page of its own.** `filament:install --panels`
scaffolds `->login()` in the panel provider, which would register a second login
at `/admin/login` — bypassing Fortify and with it 2FA, passkeys, and email
verification. That call is deliberately removed, so
`filament.admin.auth.login` does not exist and Fortify's `/login` is the only
way in. A test asserts the route stays absent.

`is_admin` is deliberately **not** mass-assignable; promote a user explicitly.

Filament **5** is required here rather than 4: Filament 4 depends on
`livewire/livewire ^3.5`, and this project is on Livewire 4.

### Dev login links

In every environment except `production`, the login page shows one-click login
buttons for the seeded accounts. This is a small first-party implementation rather than a
package — see [`config/dev-login.php`](config/dev-login.php).

`APP_ENV` is the only gate, and it is a **denylist**: `production` is the sole
blocked environment, so a bespoke name (`staging`, `demo`, `review-42`) gets the
buttons without being registered first. The route itself is only registered when
[`DevLoginAccounts::enabled()`](app/Auth/DevLoginAccounts.php) allows the current
environment, so in `production` `POST /dev-login` does not exist at all rather
than relying on a runtime guard. The check fails closed if the list is empty.

> Because the seeded credentials are fixed and public, **any deployed instance
> that is not `production` allows passwordless login to them**. That is a
> deliberate trade for demo instances — deploy anything real as `production`.

The form submits a **position** in the configured account list, never an email
address, so a crafted request cannot log into an account the application did not
offer, and no `redirect_url` is read from user input.

[`DevLoginController`](app/Http/Controllers/DevLoginController.php) and Fortify's
`LoginResponse` share the
[`ResolvesLoginRedirect`](app/Http/Responses/ResolvesLoginRedirect.php) trait, so
a dev login lands exactly where a real login would: the intended URL if one was
captured, else the admin panel for admins and the dashboard for everyone else.

### Seeding

```bash
php artisan migrate:fresh --seed
```

Two demo accounts, with **fixed credentials** defined in
[`config/first.php`](config/first.php) — no environment variables, so a fresh
clone and a fresh deployment both come up usable with nothing to configure:

| Account | Email | Password | Access |
| --- | --- | --- | --- |
| Admin | `admin@example.com` | `password` | Filament panel at `/admin` |
| User | `test@example.com` | `password` | `/dashboard` only |

`AdminUserSeeder` is idempotent: an existing account is promoted to admin but
its name and password are left alone, so a password you change is never reset by
a redeploy. `DatabaseSeeder` adds the non-admin user wherever the quick logins
are offered — every environment except `production`.

`SettingsSeeder` adds placeholder business contact details (address, phone,
email) so the public footer and the settings form have something to show. It
seeds only keys with no row yet, so details you edit from **Admin → Settings**
are never overwritten by a redeploy.

> **The credentials are public.** Change the admin password from its settings
> page once a deployed instance is reachable. Deploy real instances as
> `APP_ENV=production` (the default), which is the only environment that
> withholds the passwordless [dev login](#dev-login-links).

### Deployment

The app ships as a single image built from a four-stage
[`Dockerfile`](Dockerfile) — a shared `php-base` carrying the extension set,
then composer vendor, then assets, then runtime — which
runs three roles selected by `CONTAINER_ROLE`: the web tier (nginx + php-fpm),
a queue worker (`queue:work`), and the scheduler (`schedule:work`). See
[`docker/README.md`](docker/README.md) for the full walkthrough.

```bash
cp .env.docker.example .env.docker   # first time only
docker compose --env-file .env.docker up --build -d
open http://localhost:8011
```

`--env-file .env.docker` is required: Compose otherwise falls back to the
project-root `.env` — the application's own Laravel env — and silently builds
the throwaway stack from it. A `DOCKER_LOCAL_STACK` tripwire makes that failure
loud instead of silent.

- [`docker-compose.yml`](docker-compose.yml) — local only. Embeds a throwaway
  `APP_KEY`, a known database password, and a published port; never deploy it.
- [`docker-compose.dokploy.yml`](docker-compose.dokploy.yml) — the deploy stack.
  No database service, no published ports, and every secret comes from the
  environment. Required variables use `${VAR:?message}`, so a missing one fails
  the deploy with a named error rather than booting on a silent default.

The entrypoint waits for the database, migrates, seeds, rebuilds caches against
the real environment, and republishes Filament's assets on every boot.

#### Overriding APP_ENV for a staging instance

`APP_ENV` defaults to `production` but is overridable in Dokploy. Note what the
value controls before changing it:

| `APP_ENV` | `migrate:fresh` / `db:wipe` | Password policy | Passwordless dev login | Demo user seeded |
| --- | --- | --- | --- | --- |
| `production` | prohibited | strict | **no** | no |
| `staging` (or any other) | prohibited | strict | **yes** | yes |
| `local` / `testing` | **allowed** | **none** | **yes** | yes |

**`production` is the only value that withholds the passwordless
[dev login](#dev-login-links).** Since the seeded credentials are fixed and
public, a deployed instance on any other `APP_ENV` can be signed into by anyone
who reaches its login page. Deploy anything that is not a throwaway demo as
`production`, which is the default.

`staging` remains the right choice for a deployed **demo** that is meant to be
walked into: it keeps the destructive-command and password guards on while still
offering the one-click logins. It is not a way to get a "safer production".

**Never set `local` on a deployed instance** — on top of the dev login it
re-enables `migrate:fresh` and `db:wipe` against that database and drops the
password policy entirely.

#### Trusted proxies

[`config/trustedproxy.php`](config/trustedproxy.php) supplies the key
`Illuminate\Http\Middleware\TrustProxies` reads. It matters behind a
TLS-terminating reverse proxy: Traefik forwards plain HTTP with an
`X-Forwarded-Proto: https` header, and if that proxy is untrusted Laravel reads
the request as insecure and generates `http://` URLs on an `https://` page —
which browsers block as mixed content, taking the stylesheet and JS bundle with
them.

`TRUST_PROXIES=*` is set in the Dokploy stack and is safe there precisely
because nothing is published: only Traefik can reach the container. Leave it
unset on a same-host nginx → php-fpm deploy, where trusting those headers from
any client would let it spoof both scheme and IP. A comma-separated list names
specific proxies instead.

#### Checking a deployment's configuration

[`docker-compose.dokploy.yml`](docker-compose.dokploy.yml) is the reference for
production values — it pins `APP_DEBUG=false`, `SESSION_ENCRYPT`,
`SESSION_SECURE_COOKIE` and the rest, and fails the stack outright when a
required secret is missing. There is deliberately no `.env.production.example`
beside it; a second copy of those values would only drift.

What the compose file cannot tell you is what a *running* instance actually
resolved. **Admin → Settings → Diagnostics** audits the live configuration and
reports what is wrong: debug mode, a missing application key, SQLite in
production, unencrypted sessions or an insecure session cookie are errors; a
wildcard proxy list, a container-local image disk, the log mailer, a fallback
passkey secret and debug-level logging are warnings, since each is defensible in
some deployments.

The checks live in [`App\Settings\ProductionDiagnostics`](app/Settings/ProductionDiagnostics.php)
and read resolved config rather than the env file, so a value baked in by
`config:cache` is reported as it actually is.

### Settings

Livewire components for profile updates, security (2FA, passkeys), appearance,
and account deletion, routed from `routes/settings.php`.

### API

A deliberately thin, versioned skeleton — enough that adding the first endpoint
is writing a route, not wiring a subsystem.

- [`routes/api/v1.php`](routes/api/v1.php), registered from
  [`bootstrap/app.php`](bootstrap/app.php) under the `api` middleware group with
  the `api/v1` prefix and `api.v1.` name prefix. The version is in the filename,
  so a v2 is a new file beside it rather than a rewrite.
- [`BaseApiResource`](app/Http/Resources/BaseApiResource.php) pins the `data`
  envelope per-resource instead of relying on the global, mutable default, with
  [`UserResource`](app/Http/Resources/UserResource.php) as the worked example —
  fields listed explicitly, so a column added later is never published by
  accident, and `avatar_url` opt-in via `->withAvatarUrl()` because resolving it
  stats the images disk once per user.
- JSON error shapes come from `shouldRenderJsonWhen` in `bootstrap/app.php`;
  `ApiSkeletonTest` pins that a 404 and a 422 under `api/*` stay JSON even when
  the client asks for HTML.

Sanctum is **not** installed. Token auth is a ten-minute addition when a project
needs it, and an unused authentication surface until then; `routes/api/v1.php`
carries a commented `auth:sanctum` group showing where it goes.

### Error pages

`resources/views/errors/` styles 403, 404, 419, 429, 500 and 503 with the
application's own design system, through
[`x-errors.layout`](resources/views/components/errors/layout.blade.php). 419 is
the one users actually hit: leaving a form or a Livewire page open past the
session lifetime expires the CSRF token, and Laravel's default "Page Expired"
reads as a fault rather than as "log in again and retry".

These pages deliberately do **not** extend `x-layouts::app` or include
`partials.head`. A 500 is most often a database outage, and the
`View::composer('*')` that supplies `$businessName` reads the settings table —
inheriting it means the error view throws while rendering and the user gets
Laravel's unstyled fallback at exactly the moment these templates exist for. So
they brand from `config('app.name')`, the one intentional exception to the rule
that views read `$businessName`. `ErrorPagesTest` asserts each page still
renders against an unreachable database.

### Tests

The feature suite covers each auth flow — authentication, registration,
password reset, password confirmation, email verification, the 2FA challenge —
plus settings, the dashboard, and the admin/dev-login behaviour described above:

- `AdminPanelAccessTest` — the Filament login route stays absent, guests redirect
  to Fortify, non-admins get 403, admins get in, `is_admin` resists mass
  assignment, and each role lands on the right page after login.
- `AuthorizationTest` / `RoleTest` — role defaults and mass-assignment
  protection, permission-to-gate registration, the administrator bypass, and
  that the bypass does not override the self-deletion and self-demotion rules.
- `ErrorPagesTest` — each status code resolves to this application's template
  rather than the framework's, every page is `noindex`, and all of them render
  with the database unreachable.
- `AdminUserSeederTest` — seeding, idempotency, and the production guard.
- `DevLoginTest` — the buttons render and sign in the right account in `local`,
  an unoffered position or unseeded account 404s, a submitted email or
  `redirect_url` is ignored, and disallowed hosts and environments are refused.
- `TrustedProxiesTest` — the config key exists, `TRUST_PROXIES` parses into the
  shape the middleware expects, and asset URLs come out `https` behind a trusted
  proxy and `http` without one.

```bash
composer test        # config clear, Pint check, PHPStan, then the suite (parallel)
vendor/bin/pest      # the suite alone
```

#### Browser tests

A handful of smoke tests in [`tests/Browser`](tests/Browser) drive a real
browser through [Pest's browser plugin](https://pestphp.com/docs/browser-testing),
covering what a server-side assertion cannot see: a page that returns 200 while
throwing in the browser. They cover login, a rejected password, the 2FA
challenge screen, and that the admin panel and its settings tabs render.

```bash
npx playwright install chromium         # once
vendor/bin/pest --testsuite=Browser
```

They run as their own CI job so a browser download and the occasional browser
flake stay out of the fast feedback loop. Two limits are worth knowing before
adding more, both recorded in [`.ai/rules/browser.md`](.ai/rules/browser.md):
**file uploads do not work** (the plugin's test server discards uploaded files,
so Livewire uploads fail there while working in a real browser), and **WebAuthn
cannot be driven** (no CDP session, so no virtual authenticator). Passkey
coverage therefore stops at the server's challenge payload, in
`PasskeyLoginOptionsTest`.

## Test Impact Analysis (Tia)

Configured in [`tests/Pest.php`](tests/Pest.php). Tia records which tests touch
which files, then re-runs only the tests your latest changes affect and replays
cached results for the rest. It requires the PCOV coverage driver.

```php
pest()->tia()->defaultBranch('main')->locally();
```

**`locally()`** activates Tia on every local `pest` / `artisan test` run, with no
`--tia` flag needed.

**`defaultBranch('main')`** names the baseline branch that every other branch
falls back to reading. Without it, Tia fails on any checkout that has a remote
but no `origin/HEAD`:

```
ERROR  Tia mode could not determine the default branch.
```

The alternative is `git remote set-head origin --auto`, but that writes into
`.git/` and is therefore per-clone and uncommitted — every teammate, fresh
clone, and CI runner would hit the same error. Naming the branch in config fixes
it once for everyone, and is deterministic in CI where the checkout is often
shallow or detached with no `origin/HEAD` at all.

The tradeoff: renaming the default branch means updating this line too. That
failure is loud and immediate, unlike the silent full-suite re-runs the error
warns about.

### Tia in CI

Pest's environment defaults to LOCAL and only becomes CI when `--ci` is passed
explicitly. Pass it so the pipeline runs the full suite rather than trusting a
cached dependency graph. Because `artisan test` does not forward `--ci`, CI
should invoke Pest directly:

```bash
vendor/bin/pest --ci
```

## Continuous integration

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push to
`main` and every pull request:

- **Pint & PHPStan** — `pint --test` and `phpstan analyse` (level 8).
- **Pest** — the suite on sqlite, in parallel.
- **Pest (mysql / mariadb)** — the same suite against `mysql:8.4` and
  `mariadb:11`.

That last job exists because production runs MySQL or MariaDB (the Dockerfile
installs `pdo_mysql`) while the fast job runs sqlite, which tolerates looser
typing and different strict-mode and DDL behaviour. A green sqlite suite is not
evidence the deployed database agrees.

The DB settings are passed as job environment variables, which works because
PHPUnit's `<env>` elements do not overwrite a variable already set in the
process environment. `DB_URL` is blanked for the same reason: `phpunit.xml` sets
it empty, and a non-empty `DB_URL` would take precedence over host and port.

[`.github/dependabot.yml`](.github/dependabot.yml) tracks Composer, npm, GitHub
Actions and the Dockerfile's pinned base images, grouping the first-party
Laravel packages so they land as a set.

## Code style and static analysis

```bash
composer lint         # Pint, fixing in place
composer lint:check   # Pint, check only
composer types:check  # PHPStan
```

Pint runs against [`pint.json`](pint.json). Blade and Tailwind formatting is
handled by Prettier with `prettier-plugin-blade` and
`prettier-plugin-tailwindcss`.

## AI agent configuration

[`CLAUDE.md`](CLAUDE.md) carries the Laravel Boost guidelines — conventions,
Artisan and Pint usage, and testing expectations for agents working in this
repo.

Durable project-specific rules live in [`.ai/rules`](.ai/rules/), grouped by area
and mapped to file globs in [`.ai/rules/index.md`](.ai/rules/index.md). Agents
read the rule files matching the paths they are about to touch.

- [`filament.md`](.ai/rules/filament.md) — why the admin panel must never enable
  Filament's own login page, and what to re-check after regenerating a panel.

Record new rules with Boost's `record-rule` tool rather than editing these files
by hand, so they are committed and shared with the team rather than scoped to one
session.
