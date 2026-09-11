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

### Admin panel

Filament 5 serves an admin panel at `/admin`. Access is gated on `users.is_admin`
in two places: `User::canAccessPanel()` (Filament's `FilamentUser` contract) and
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

> **The credentials are public.** Change the admin password from its settings
> page once a deployed instance is reachable. Deploy real instances as
> `APP_ENV=production` (the default), which is the only environment that
> withholds the passwordless [dev login](#dev-login-links).

### Deployment

The app ships as a single image built from a three-stage
[`Dockerfile`](Dockerfile) — composer vendor, then assets, then runtime — which
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

### Settings

Livewire components for profile updates, security (2FA, passkeys), appearance,
and account deletion, routed from `routes/settings.php`.

### Tests

The feature suite covers each auth flow — authentication, registration,
password reset, password confirmation, email verification, the 2FA challenge —
plus settings, the dashboard, and the admin/dev-login behaviour described above:

- `AdminPanelAccessTest` — the Filament login route stays absent, guests redirect
  to Fortify, non-admins get 403, admins get in, `is_admin` resists mass
  assignment, and each role lands on the right page after login.
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
