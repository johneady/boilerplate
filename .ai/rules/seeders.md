---
paths:
  - 'database/seeders/**'
---

# Seeders

## Seeders that run in production must not use factories
Model factories call fake(), which comes from fakerphp/faker — a require-dev package. A production image built with `composer install --no-dev` has no faker, so any seeder the container entrypoint runs dies with "Call to undefined function Database\Factories\fake()" and crashloops the container.

AdminUserSeeder therefore builds its User with `new User` + explicit assignment (is_admin is not mass-assignable, so set it as a property, and let the model's 'hashed' cast handle the password).

Factories are fine in seeders gated to local/testing, as DatabaseSeeder's test user is.
