---
paths:
  - 'app/Providers/Filament/**'
---

# Filament

## Never enable Filament's own login page
Fortify's /login is the ONLY login page. Filament's panel generator emits `->login()` for the default panel (see PanelProviderClassGenerator), which registers a second login at /admin/login that bypasses Fortify — and with it 2FA, passkeys, and email verification.

If you re-run `filament:install --panels` or regenerate a panel provider, delete the scaffolded `->login()` line again. tests/Feature/AdminPanelAccessTest.php asserts route `filament.admin.auth.login` does not exist.

Access is enforced in exactly one place: User::canAccessPanel() (the FilamentUser contract), which the parent Filament\Http\Middleware\Authenticate aborts on with 403. App\Http\Middleware\FilamentAuthenticate exists only to override redirectTo() so guests are sent to route('login') — that override is load-bearing, because the panel has no login route of its own and the parent default would send guests to a missing route. Do not re-add a redundant is_admin check to the middleware; if canAccessPanel ever becomes a role check, keeping one enforcement point is what makes that change safe.
