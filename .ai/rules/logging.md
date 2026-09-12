---
paths:
  - config/logging.php
  - .env.example
  - docker-compose.yml
  - docker-compose.dokploy.yml
---

# Logging

## Deployed logging uses the daily channel, not single
LOG_STACK=daily with LOG_DAILY_DAYS (14) in .env.example and both compose files. Nothing rotates a log inside a container, so 'single' grows for the life of the volume until it fills the disk.

Not stderr, though that is the usual container answer: laravel/pail and Boost's log reader both need a file, and Docker log retention is host config this repo does not control — that would trade unbounded growth for unbounded-or-zero.

.env.example is set to daily too, deliberately. It is what gets copied into projects built on this boilerplate, so leaving it on 'single' would propagate the unbounded default.

tests/Feature/LoggingTest.php asserts daily resolves to a RotatingFileHandler and single does not.
