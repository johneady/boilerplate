---
paths:
  - 'app/Settings/Settings.php, tests/Feature/SettingsTest.php, tests/Feature/ErrorPagesTest.php'
---

# Feature

## Settings reads degrade to defaults on a connection failure, not on SQL errors
Settings::all() catches QueryException and returns [] -- so every key falls back to its declared default -- but ONLY when isConnectionFailure() says the database was unreachable. This is load-bearing for the error pages: the global View::composer('*') resolves businessName() for EVERY view, so without it a 500 caused by the database throws again inside the 500 page and the user gets Laravel's unstyled fallback.

Match on the framework's typed exceptions first (LostConnectionException, SQLiteDatabaseDoesNotExistException): a missing SQLite file arrives with getCode() 0 and NO errorInfo, so sniffing vendor error numbers alone misses exactly the case that matters. SQLSTATE 08xxx and MySQL 1045/2002/2003/2006 cover a server that is up but unreachable. The empty result is deliberately NOT cached, so a transient outage does not pin the process to defaults.

"No such table" is deliberately NOT swallowed -- that means migrations have not run, which must stay loud rather than quietly serving defaults. tests/Feature/SettingsTest.php covers both directions.

TESTING TRAP, cost several iterations: do NOT simulate an outage by breaking the connection the suite is using. RefreshDatabase holds a transaction on it, and DB::purge()/DB::reconnect() on the default :memory: sqlite opens a BRAND NEW empty database -- discarding the migrated schema and failing every later test in the process with "cannot start a transaction within a transaction" or "table migrations already exists". Instead define a throwaway 'unreachable_test' connection, point database.default at it, and restore database.default in a finally block: RefreshDatabase resolves the connection it rolls back at teardown FROM database.default, so leaving the bogus one set rolls back the wrong connection. Each test passes in isolation either way, so this only shows up when the file is run whole.
