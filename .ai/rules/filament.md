---
paths:
  - 'app/Providers/Filament/**'
---

# Filament

## Never enable Filament's own login page
Fortify's /login is the ONLY login page. Filament's panel generator emits `->login()` for the default panel (see PanelProviderClassGenerator), which registers a second login at /admin/login that bypasses Fortify — and with it 2FA, passkeys, and email verification.

If you re-run `filament:install --panels` or regenerate a panel provider, delete the scaffolded `->login()` line again. tests/Feature/AdminPanelAccessTest.php asserts route `filament.admin.auth.login` does not exist.

Access is gated in two places instead: User::canAccessPanel() (FilamentUser contract) and App\Http\Middleware\FilamentAuthenticate, whose redirectTo() sends guests to route('login'). That redirect is load-bearing — without it guests hit a missing route.
