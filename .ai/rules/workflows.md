---
paths:
  - '.github/workflows/**, .github/dependabot.yml'
---

# Workflows

## CI runs Pest directly and tests against MySQL and MariaDB
The tests job calls `vendor/bin/pest --ci --parallel`, NOT `artisan test`: Pest's environment defaults to LOCAL and only becomes CI when --ci is passed, and artisan test does not forward the flag -- without it CI trusts Tia's cached dependency graph instead of running the full suite. The checkout uses fetch-depth: 0 because Tia diffs against origin/main, and PCOV (not xdebug) because Tia requires it.

A separate `database` job runs the suite against mysql:8.4 and mariadb:11, because production runs one of those (the Dockerfile installs pdo_mysql) while the fast job runs sqlite, which silently tolerates looser typing and different strict-mode/DDL behaviour. Both were verified green locally (384 tests at the time).

That job sets DB_* as job env vars, which works because PHPUnit's <env> elements in phpunit.xml do NOT overwrite a variable already present in the process environment (only force="true" would). DB_URL must be set empty too -- phpunit.xml sets it empty and a non-empty DB_URL takes precedence over host/port.

The service healthcheck is an `||` of MariaDB's healthcheck.sh and mysqladmin: MySQL 8.4 ships only the latter. MySQL 8.4 also defaults to caching_sha2_password, so root-over-socket can be denied where MariaDB allows it -- the app connects over TCP, which is unaffected.
