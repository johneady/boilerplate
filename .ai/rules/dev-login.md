---
paths:
  - app/Auth/DevLoginAccounts.php
  - app/Http/Controllers/DevLoginController.php
  - app/Providers/AppServiceProvider.php
  - config/dev-login.php
  - docker-compose.dokploy.yml
  - routes/web.php
---

# Dev login

## The route must stay registered conditionally, not guarded at runtime
routes/web.php registers `POST /dev-login` only when `DevLoginAccounts::enabled()`
passes, so the passwordless login route does not exist in production at all.
Moving the environment check into the controller (or registering the route
unconditionally "and letting the guard handle it") turns a missing route into a
live endpoint whose safety depends on one `if` — the failure mode being a
one-click admin login reachable in production.

## The request selects an account by position, never by email
The form posts `account` — an index into `config('dev-login.accounts')` — and the
controller resolves the email server-side. This is the deliberate difference from
`spatie/laravel-login-link`, which was replaced here: that package read `email`,
`user_model` and `redirect_url` straight from the request body, so anything that
could reach the endpoint could log into an arbitrary account and bounce to an
arbitrary URL. Accepting an email or a redirect target from user input
reintroduces both holes; tests/Feature/DevLoginTest.php covers them.

## APP_ENV is overridable on Dokploy, so env guards must not key on isProduction()
docker-compose.dokploy.yml defaults APP_ENV to production but allows an override
for staging instances. Any guard written as `app()->isProduction()` therefore
switches off the moment someone sets APP_ENV=staging on a real deployment.
`DB::prohibitDestructiveCommands()` is deliberately written as
`! app()->environment(['local', 'testing'])` for that reason: staging owns a real
database, and migrate:fresh must stay prohibited there. Guard new destructive or
data-exposing behaviour by naming the safe environments, not by excluding
production. tests/Feature/AppDefaultsTest.php covers staging explicitly.

## APP_ENV is the only dev-login gate, and the allow list is deliberately hardcoded
config/dev-login.php hardcodes ['local', 'testing']. An earlier revision added
DEV_LOGIN_ENVIRONMENTS/DEV_LOGIN_HOSTS plus a host allow-list as a second gate;
both were removed on request, leaving APP_ENV as the single gate. Do not
reintroduce a config toggle for the environment list.

The consequence is that APP_ENV alone decides this, and
docker-compose.dokploy.yml makes APP_ENV overridable: setting APP_ENV=local on a
deployed instance puts a passwordless one-click admin login on that public URL,
with nothing else stopping it. That trade-off is accepted deliberately. Anything
that widens it -- defaulting APP_ENV to something other than production, or
adding an environment to the allow list -- needs the same explicit decision, not
a convenience commit.
