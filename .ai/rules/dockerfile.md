---
paths:
  - Dockerfile
---

# Dockerfile

## The image runs against MariaDB, not SQLite
The runtime stage installs pdo_mysql only. Both compose files set `DB_CONNECTION: mariadb`, so the container never uses the `sqlite` default from config/database.php — that default is for local development.

If you ever do need pdo_sqlite in the image, `docker-php-ext-install pdo_sqlite` fails at configure time ("Package 'sqlite3' ... not found") unless `libsqlite3-dev` is added to the same apt-get install line; the php:8.5-fpm-bookworm base ships no sqlite headers. Check any other pdo_* driver for its -dev package the same way. pdo_mysql happens not to need one.
