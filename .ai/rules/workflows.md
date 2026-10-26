---
paths:
  - '.github/workflows/**, .github/dependabot.yml'
---

# Workflows

## CI runs Pest directly, in ONE job, against MariaDB 11.8 only
The suite step calls `vendor/bin/pest --ci --parallel`, NOT `artisan test`: Pest's environment defaults to LOCAL and only becomes CI when --ci is passed, and artisan test does not forward the flag -- without it CI trusts Tia's cached dependency graph instead of running the full suite. The checkout uses fetch-depth: 0 because Tia diffs against origin/main, and PCOV (not xdebug) because Tia requires it.

ci.yml is one `checks` job (Pint, PHPStan, Prettier, Unit+Feature on MariaDB, then Browser) plus the `publish` call into docker.yml. It used to be four jobs: static, a sqlite run, browser, and a mysql:8.4/mariadb:11/pgsql matrix. Actions bills each job separately, rounded up to a whole minute, and each repeated the full PHP/Composer/npm/Vite setup -- multiplied by every Dependabot PR and rebase, that exhausted a private repo's free minutes (Oct 2026). Do not split it back into parallel jobs to shorten the wall clock; the trade was made for minutes.

MariaDB 11.8 is the only server because production runs it (Dokploy's managed DB, docker-compose.yml) with DB_CONNECTION=mariadb -- so CI uses the `mariadb` driver, not `mysql`. sqlite, MySQL and PostgreSQL legs were dropped deliberately: nothing deploys on them. sqlite stays the LOCAL default in phpunit.xml, and the browser suite still runs on it (it tests the UI, not SQL). The pgsql branches in app code (ServerInformation::pgsqlSize, the savepoint notes in Payments) are now untested in CI.

The suite step sets DB_* as step env vars, which works because PHPUnit's <env> elements in phpunit.xml do NOT overwrite a variable already present in the process environment (only force="true" would). DB_URL must be set empty too -- phpunit.xml sets it empty and a non-empty DB_URL takes precedence over host/port.

The service healthcheck is MariaDB's own `healthcheck.sh --connect --innodb_initialized`. The MariaDB 11 image ships no `mysql`/`mysqladmin` binaries (only `mariadb`/`mariadb-admin`), so a copied MySQL-style healthcheck or GRANT step fails there.

## Runner minutes: what does and does not trigger a run
- ci.yml ignores `**.md` and `.ai/**` (push and PR). A docs-only merge publishes no image -- correct, the image would be identical.
- docker.yml's PR `paths` deliberately exclude composer/package manifests and lockfiles, so Dependabot PRs do not pay a 5-15 minute image build. The publish build on main still runs the extension assertion before anything reaches GHCR.
- dependabot.yml is monthly with one group per ecosystem; see its header comment before splitting a group.
- `runs-on: ${{ vars.CI_RUNNER || 'ubuntu-latest' }}` in both workflows: set the CI_RUNNER repository variable to `self-hosted` to move onto a self-hosted runner, delete it to fall back.
