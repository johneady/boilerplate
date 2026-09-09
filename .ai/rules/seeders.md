---
paths:
  - 'database/seeders/**'
---

# Seeders

## Seeders that run in production must not use factories
Model factories call fake(), which comes from fakerphp/faker — a require-dev package. A production image built with `composer install --no-dev` has no faker, so any seeder the container entrypoint runs dies with "Call to undefined function Database\Factories\fake()" and crashloops the container.

AdminUserSeeder therefore builds its User with `new User` + explicit assignment (is_admin is not mass-assignable, so set it as a property, and let the model's 'hashed' cast handle the password).

Factories are fine in seeders gated to local/testing, as DatabaseSeeder's test user is.

## AdminUserSeeder must assert admin rights on an existing account
The seeder is idempotent, but "already exists" must not mean "do nothing". A first
user created by UserFactory (which defaults is_admin to false) occupies the
FIRST_USER_EMAIL address without admin rights, so an early `return` on existence
leaves that address permanently unable to reach the panel — login then redirects to
/dashboard and looks like a broken redirect rather than a seeding problem.

Re-seeding therefore promotes an existing account, while still leaving its name and
password alone so an operator's own changes are not reset.

DatabaseSeeder's local-only test user also skips itself when FIRST_USER_EMAIL is
test@example.com, so the non-admin factory user cannot land on top of the admin.
