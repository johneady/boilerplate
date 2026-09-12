---
paths:
  - 'app/Providers/Filament/**'
---

# Filament

## Never enable Filament's own login page
Fortify's /login is the ONLY login page. Filament's panel generator emits `->login()` for the default panel (see PanelProviderClassGenerator), which registers a second login at /admin/login that bypasses Fortify — and with it 2FA, passkeys, and email verification.

If you re-run `filament:install --panels` or regenerate a panel provider, delete the scaffolded `->login()` line again. tests/Feature/AdminPanelAccessTest.php asserts route `filament.admin.auth.login` does not exist.

Access is enforced in exactly one place: User::canAccessPanel() (the FilamentUser contract), which the parent Filament\Http\Middleware\Authenticate aborts on with 403. App\Http\Middleware\FilamentAuthenticate exists only to override redirectTo() so guests are sent to route('login') — that override is load-bearing, because the panel has no login route of its own and the parent default would send guests to a missing route. Do not re-add a redundant is_admin check to the middleware; keeping one enforcement point is what made the move to roles safe. canAccessPanel() now asks `hasPermission(Permission::AccessAdminPanel)` rather than reading a boolean column -- see .ai/rules/providers.md.

Colours used by badges must be registered on the panel's ->colors(). Filament emits a colour's CSS custom properties only for registered colours, so a badge naming an unregistered one (the role badges use amber and zinc) renders with its fi-color-* class applied but no colour behind it: visibly flat, with nothing in the markup to explain why. tests/Feature/AuthorizationTest.php asserts every Role::color() is registered.

## Panel brandName must stay a closure
->brandName() is passed a closure resolving App\Settings\Settings::businessName(). The panel is configured once per process, so resolving the setting eagerly there would pin the brand to whatever was stored at boot and ignore later edits — which a long-lived worker or Octane process would serve indefinitely.

tests/Feature/AdminPanelAccessTest.php ('the panel brand follows a business name changed after boot') covers this.
