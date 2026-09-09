---
paths:
  - app/Http/Middleware/EnsureRegistrationIsEnabled.php
---

# Middleware

## Public sign-up is gated at runtime, not by Fortify's feature flag
Fortify's Features::registration() is read at boot to decide whether the register routes exist, so it cannot answer a setting an admin flips at runtime. Do not try to toggle it from the database.

Instead the routes stay registered (route('register') keeps resolving, so views and redirects do not break) and EnsureRegistrationIsEnabled — appended to the `web` group in bootstrap/app.php, self-scoped by route name to `register` and `register.store` — 404s both while SettingKey::AllowRegistration is false. The POST is gated as well as the GET, so a stale open form cannot create an account.

Views hide the sign-up links with the @registrationEnabled Blade condition (registered in AppServiceProvider), so the links disappear alongside the routes.

The default is false, so tests that exercise the registration flow must turn it on first — see tests/Feature/Auth/RegistrationTest.php and HomePageTest.php.
