---
paths:
  - '.github/workflows/**, .github/dependabot.yml'
  - 'package/**'
  - release-please-config.json
---

# Workflows

## CI runs Pest directly, in ONE job, against MariaDB 11.8 only
The suite step calls `vendor/bin/pest --ci --parallel`, NOT `artisan test`: Pest's environment defaults to LOCAL and only becomes CI when --ci is passed, and artisan test does not forward the flag -- without it CI trusts Tia's cached dependency graph instead of running the full suite. The checkout uses fetch-depth: 0 because Tia diffs against origin/main, and PCOV (not xdebug) because Tia requires it.

ci.yml is one `checks` job (Pint, PHPStan, Prettier, Unit+Feature on MariaDB, then Browser) plus the `publish` call into docker.yml. It used to be four jobs: static, a sqlite run, browser, and a mysql:8.4/mariadb:11/pgsql matrix. Actions bills each job separately, rounded up to a whole minute, and each repeated the full PHP/Composer/npm/Vite setup -- multiplied by every Dependabot PR and rebase, that exhausted a private repo's free minutes (Oct 2026). Do not split it back into parallel jobs to shorten the wall clock; the trade was made for minutes.

MariaDB 11.8 is the only server because production runs it (Dokploy's managed DB, docker-compose.yml) with DB_CONNECTION=mariadb -- so CI uses the `mariadb` driver, not `mysql`. sqlite, MySQL and PostgreSQL legs were dropped deliberately: nothing deploys on them. sqlite stays the LOCAL default in phpunit.xml, and the browser suite still runs on it (it tests the UI, not SQL). The pgsql branches in app code (ServerInformation::pgsqlSize, the savepoint notes in Payments) are now untested in CI.

The suite step sets DB_* as step env vars, which works because PHPUnit's <env> elements in phpunit.xml do NOT overwrite a variable already present in the process environment (only force="true" would). DB_URL must be set empty too -- phpunit.xml sets it empty and a non-empty DB_URL takes precedence over host/port.

The service healthcheck is MariaDB's own `healthcheck.sh --connect --innodb_initialized`. The MariaDB 11 image ships no `mysql`/`mysqladmin` binaries (only `mariadb`/`mariadb-admin`), so a copied MySQL-style healthcheck or GRANT step fails there.

## The CI database lives in memory, and must stay there
The MariaDB service's data directory is a tmpfs (`--tmpfs /var/lib/mysql` in its options). Each parallel worker migrates its database inside its FIRST test, so the migration counts against phpunit.xml's 60s per-test limit (enforceTimeLimit). On a self-hosted runner with slow storage -- in appt-scheduler, a ZFS mirror of two spinning drives -- four workers migrating at once took over a minute: PHPUnit aborted the migration half-way ("Execution aborted after 60 seconds"), every later test retried it and was aborted too, and the run looked hung until the job timed out, silently skipping the release job (Nov 2026). In memory the suite ran in about 4 minutes on that runner. Do not raise the per-test limit instead: it is what catches a genuinely hung test.

## Runner minutes: what does and does not trigger a run
- ci.yml ignores `**.md` and `.ai/**` (push and PR). A docs-only merge publishes no image -- correct, the image would be identical.
- docker.yml's PR `paths` deliberately exclude composer/package manifests and lockfiles, so Dependabot PRs do not pay a 5-15 minute image build. The publish build on main still runs the extension assertion before anything reaches GHCR.
- dependabot.yml is monthly with one group per ecosystem; see its header comment before splitting a group.
- `runs-on: ${{ vars.CI_RUNNER || 'ubuntu-latest' }}` in both workflows: set the CI_RUNNER repository variable to `self-hosted` to move onto a self-hosted runner, delete it to fall back.

## Releases and the package come from the toolkit's release.yml, gated on the checks
ci.yml's `release` job calls `johneady/installer-toolkit/.github/workflows/release.yml` (as appt-scheduler and pet-adoption do) after `checks` and, on a release commit, `publish`: release-please, then on the run that cuts vX.Y.Z the toolkit's package job builds the tag and attaches `boilerplate-vX.Y.Z-full.zip` and the signed `boilerplate-vX.Y.Z.update`. The package job fails the release if no `.update` named after the tag was built, so config/app.php's `'version'` line must keep its `// x-release-please-version` marker (release-please-config.json's extra-files rewrites it). package.yml calls the same workflow with `rebuild-tag` to rebuild an existing release by hand. Both pin the toolkit tag composer.lock installs: bump the pins with the composer package. Call release.yml, not build-package.yml, which the toolkit documents as internal.

The signing key is the UPDATE_SIGNING_KEY secret of this repo's own `release` environment (deployment branches: main only), never a repository secret: PR and Dependabot jobs run third-party code and must not be able to read it. Environment secrets are per repo -- it is the same key as appt-scheduler's and pet-adoption's (key id key-2026-07-b, package/package-config.php), but each repo holds its own copy. It reaches the toolkit's package job only through `secrets: inherit`; naming `secrets.UPDATE_SIGNING_KEY` in the caller passes an empty value, because the caller's job is not in that environment. Without the key `package:build` fails rather than ship an unsigned .update.

## The release PR's failed CI run is expected
release-please opens its PR with GITHUB_TOKEN, so the PR's CI run is held for approval, runs no jobs, and is closed as "failure" ("a workflow file issue") when the PR merges. Do not approve it or try to fix it: the merge to main runs the full checks before release-please tags. Running real CI on the release PR would need an App/PAT token, and its tag would then also fire docker.yml's tag build.
