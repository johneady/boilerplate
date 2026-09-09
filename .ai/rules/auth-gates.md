---
paths:
  - app/Models/User.php
  - app/Providers/AppServiceProvider.php
  - app/Livewire/Settings/Security.php
  - routes/settings.php
---

# Auth gates

## User must implement MustVerifyEmail or the verified middleware is inert
Illuminate's EnsureEmailIsVerified only redirects users who `instanceof MustVerifyEmail`. The `verified` middleware on the dashboard and settings routes (and Fortify's email-verification feature) look enforcing while User does not implement the interface, but let everyone through — exactly the failure mode that shipped originally. Removing the interface "to simplify" silently re-opens every gated route; tests/Feature/Auth/EmailVerificationTest.php covers the redirect.

## password.confirm must stay on Livewire's persistent middleware list
Livewire re-runs only the middleware on its persistent list when handling /livewire/update requests; everything else from the original route is dropped. The `password.confirm` gate on routes/settings.php therefore protects the Security page's initial render alone unless AppServiceProvider::configurePersistentMiddleware() keeps registering Illuminate\Auth\Middleware\RequirePassword on that list. Removing that registration looks like dead code cleanup but re-enables 2FA disable/passkey-delete on stale snapshots without a recent confirmation.

## Password changes and resets must retire sessions and remember tokens
Security::updatePassword() and ResetUserPassword both purge the user's database-backed sessions (all of them on reset, all but the current one on change) and rotate remember_token. A stolen session or "remember me" cookie must not outlive a password change. The purge is guarded by `config('session.driver') === 'database'` — if the session driver ever changes, that guard needs a handler for the new driver, not deletion.
