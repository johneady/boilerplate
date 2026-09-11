---
paths:
  - Dockerfile
---

# Dockerfile

## The image runs against MariaDB, not SQLite
The runtime stage installs pdo_mysql only. Both compose files set `DB_CONNECTION: mariadb`, so the container never uses the `sqlite` default from config/database.php — that default is for local development.

If you ever do need pdo_sqlite in the image, `docker-php-ext-install pdo_sqlite` fails at configure time ("Package 'sqlite3' ... not found") unless `libsqlite3-dev` is added to the same apt-get install line; the php:8.5-fpm-bookworm base ships no sqlite headers. Check any other pdo_* driver for its -dev package the same way. pdo_mysql happens not to need one.

## gd must be configured --with-jpeg --with-webp
intervention/image decodes and re-encodes user uploads through gd. `docker-php-ext-install gd` compiles WITHOUT jpeg/webp support unless `docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype` runs first (needs libjpeg-dev, libpng-dev, libwebp-dev, libfreetype6-dev on the same apt line).

Dropping the configure step does not fail the build. It fails later, on the worker, when a real photo arrives — the same shape of trap as the pdo_sqlite/libsqlite3-dev note above. Verify after any change to the runtime stage:

  docker run --rm --entrypoint php <image> -r 'var_dump(gd_info());'

JPEG, PNG and WebP Support must all be true. Note the image ENTRYPOINT intercepts commands and exits on a missing APP_KEY, so `--entrypoint php` is required to check.
