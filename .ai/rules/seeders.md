---
paths:
  - 'database/seeders/**'
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

## The admin credentials are fixed, not environment-driven
config/first.php holds literal values and calls no env(). There is deliberately no FIRST_USER_* to set: this is a boilerplate, and every deployment is a demo instance that must come up usable with zero configuration. The seeder's old "refuse to run with default credentials outside local/testing" guard was removed with that change -- do not reinstate it without also restoring a way to configure the credentials, or seeding will simply fail on every deploy.

The consequence is that every instance has a PUBLICLY KNOWN admin login. That is accepted, and the mitigation is documented rather than enforced: change the password from the account's settings page, which re-seeding never resets.

## The demo user is seeded wherever quick logins are offered
DatabaseSeeder gates its non-admin user on DevLoginAccounts::enabled(), not on local/testing, so the account exists everywhere the login page advertises a one-click button for it. Gating the two differently is what leaves a "Not seeded" button on a deployed demo box.

It is therefore built with `new User` rather than User::factory(): it now runs inside the --no-dev production image, where fakerphp/faker is absent. tests/Feature/AdminUserSeederTest.php asserts the file contains no factory() call.
