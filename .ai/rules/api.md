---
paths:
  - 'app/Http/Middleware/ThrottleSensitiveAuthRequests.php, app/Providers/FortifyServiceProvider.php, bootstrap/app.php, routes/api/**'
---

# Api

## Register, password-reset and the API group need throttles this app adds itself
Fortify throttles login, two-factor, passkeys and email verification via config('fortify.limiters'). It ships register.store, password.email and password.update with a `guest` guard and NO limiter, and no config key to add one — see vendor/laravel/fortify/routes/routes.php. Do not republish that route file to fix it; App\Http\Middleware\ThrottleSensitiveAuthRequests is appended to the `web` group and self-scopes by ROUTE NAME (same pattern as EnsureRegistrationIsEnabled), delegating to Laravel's ThrottleRequests with named limiters so 429s, Retry-After and X-RateLimit-* behave as on Fortify's own routes. Limits live in FortifyServiceProvider::configureRateLimiting() so every rate limit is read in one place; adding a route means an entry in LIMITERS plus a RateLimiter::for().

The middleware passes an ALREADY-AUTHENTICATED request through uncounted. It runs from the `web` group, ahead of the route-level `guest` middleware, so a signed-in user's POST is rejected by RedirectIfAuthenticated before the controller — verified: no reset mail is sent, no password changes, no account is created. Counting them would let a signed-in user or a stale background tab exhaust an IP-keyed bucket that guests share, locking real visitors on the same NAT out of password resets. Note this also means a test exercising the register limit must use FAILING attempts: a successful registration calls guard->login(), after which the guest middleware turns away every later request.

password-reset-link returns TWO limits (per-address AND per-IP): per-address alone lets one client enumerate accounts across many addresses, per-IP alone lets many IPs flood one mailbox. Its key is lowercased via Str::transliterate(Str::lower(...)) — without that, changing capitalisation buys a fresh bucket and the per-address limit is decorative.

Separately: since Laravel 11 the `api` middleware group is only SubstituteBindings — it carries NO throttle, unlike Laravel 10. bootstrap/app.php applies 'throttle:api' at the versioned group so new endpoints are covered by default; the limiter is in AppServiceProvider::configureRateLimiting().

tests/Feature/ThrottlingTest.php drives real requests rather than seeding limiter buckets: ThrottleRequests hashes its cache key as md5($limiterName.$key), so a test that writes that key asserts a framework internal and passes even when the middleware is detached. Verified 7/8 of those tests fail when the wiring is removed.
