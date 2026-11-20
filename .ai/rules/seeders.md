---
paths:
  - 'database/seeders/**'
  - config/first.php
  - 'package/**'
---

# Seeders

## Seeders that run in production must not use factories
Model factories call fake(), which comes from fakerphp/faker — a require-dev package. A production image built with `composer install --no-dev` has no faker, so any seeder the container entrypoint runs dies with "Call to undefined function Database\Factories\fake()" and crashloops the container.

AdminUserSeeder therefore builds its User with `new User` + explicit assignment (is_admin is not mass-assignable, so set it as a property, and let the model's 'hashed' cast handle the password). DatabaseSeeder's demo user does the same, and for the same reason.

NEITHER seeder may use a factory any more. DatabaseSeeder's user was factory-built while it was gated to local/testing; it is now seeded in every environment but production (see below), so it runs inside the --no-dev image and a factory there would crashloop the container. A factory is only safe in a seeder that can never run in the deployed image.

## AdminUserSeeder must assert admin rights on an existing account
The seeder is idempotent, but "already exists" must not mean "do nothing". A first
user created by UserFactory (which defaults is_admin to false) occupies the
configured admin address without admin rights, so an early `return` on existence
leaves that address permanently unable to reach the panel — login then redirects to
/dashboard and looks like a broken redirect rather than a seeding problem.

Re-seeding therefore promotes an existing account, while still leaving its name and
password alone so an operator's own changes are not reset.

DatabaseSeeder's demo user also skips itself when the admin address is
test@example.com, so the non-admin user cannot land on top of the admin.

## The admin comes from the installer, else the fixed demo values
config/first.php reads FIRST_USER_EMAIL / FIRST_USER_FIRST_NAME / FIRST_USER_LAST_NAME / FIRST_USER_PASSWORD (and FIRST_USER_NAME), which installer-toolkit's install.php writes from its admin step. With the keys MISSING (Docker, a fresh clone) it falls back to the fixed demo admin, so a deployed instance still comes up usable with zero configuration -- and with a PUBLICLY KNOWN login, mitigated by changing the password from the account's settings page, which re-seeding never resets.

A key PRESENT BUT BLANK is not "missing": after seeding, the installer scrubs the values to `""` and keeps the keys. config/first.php then yields an empty email and AdminUserSeeder seeds nothing. Do not "simplify" the fallback to `env(...) ?: 'admin@example.com'`: on an installed copy that would create a second admin with password `password` the next time anything runs the seeder. env()'s own default (missing key only) is what draws that line; tests/Feature/AdminUserSeederTest.php covers all three cases. docker-compose.dokploy.yml passes FIRST_USER_* with `:-` defaults for the same reason: a blank Dokploy value must fall back, not read as a scrubbed install.

The seeder's old "refuse to run with default credentials outside local/testing" guard stays removed: Docker deployments seed the demo admin on purpose.

## The installer seeds essentials only
package/package-config.php's sample_seeders is empty on purpose. PlanSeeder, BlogSeeder and DemoBusinessSeeder are written for demo instances: BlogSeeder attributes posts to the quick-login demo accounts by email, and those accounts have public passwords, so they must not be put on a buyer's install. DatabaseSeeder still runs the demo set wherever DevLoginAccounts::enabled(), and the installer never calls DatabaseSeeder -- it runs the listed classes with APP_ENV=production.

## The demo user is seeded wherever quick logins are offered
DatabaseSeeder gates its non-admin user on DevLoginAccounts::enabled(), not on local/testing, so the account exists everywhere the login page advertises a one-click button for it. Gating the two differently is what leaves a "Not seeded" button on a deployed demo box.

It is therefore built with `new User` rather than User::factory(): it now runs inside the --no-dev production image, where fakerphp/faker is absent. tests/Feature/AdminUserSeederTest.php asserts the file contains no factory() call.
