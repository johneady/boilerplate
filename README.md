# Boilerplate

A Laravel 13 starting point with authentication, a Livewire + Flux UI frontend, and a
test suite wired for Test Impact Analysis.

## Stack

| Layer     | Choice                                                       |
| --------- | ------------------------------------------------------------ |
| Runtime   | PHP 8.5, Laravel 13                                          |
| Frontend  | Livewire 4, Flux UI 2, Tailwind CSS 4, Vite 8 (`vite-plus`)  |
| Auth      | Laravel Fortify                                              |
| Testing   | Pest 5 with the Tia engine                                   |
| Static    | Larastan / PHPStan                                           |
| Style     | Laravel Pint                                                 |
| AI tools  | Laravel Boost, `.ai/rules`                                   |

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
- Passkeys / WebAuthn, via `@laravel/passkeys`

Fortify actions live in `app/Actions/Fortify`, with shared validation rules
extracted into `app/Concerns` (`PasswordValidationRules`,
`ProfileValidationRules`).

### Settings

Livewire components for profile updates, security (2FA, passkeys), appearance,
and account deletion, routed from `routes/settings.php`.

### Tests

Feature tests cover each auth flow — authentication, registration, password
reset, password confirmation, email verification, the 2FA challenge — plus
settings and the dashboard.

```bash
composer test        # config clear, Pint check, PHPStan, then the suite
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

Durable project-specific rules are recorded with Boost's `record-rule` tool,
which writes them to `.ai/rules` grouped by area and mapped to file globs in
`.ai/rules/index.md`. That directory does not exist yet; the first recorded rule
creates it. Prefer `record-rule` over per-agent memory so rules are committed and
shared with the team rather than scoped to one session.
