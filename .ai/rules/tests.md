---
paths:
  - 'tests/**'
---

# Tests

## Never assert a json column's array with toBe() — MySQL reorders object keys
A `$table->json()` column is native JSON on MySQL 8 / MariaDB, which returns object keys in its own order. SQLite stores the text verbatim, so key order survives there. An order-sensitive assertion on such a column therefore passes the fast sqlite job and fails the MySQL/MariaDB matrix in .github/workflows/ci.yml — green locally, red in CI.

toBe() compares with === (order-sensitive for string keys); toEqualCanonicalizing() compares with == (order-insensitive) and still fails on a wrong key or value, so nothing is weakened. Hit on Media::$conversions in ProcessUploadedImageTest.

Applies to every json column asserted as a multi-key array: media.conversions, audit_logs.old_values/new_values. Single-key and empty arrays cannot differ and are fine as toBe(). Only relax ordering where nothing reads the map positionally — these are {name: path} lookups, read by key.
