<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limit the Fortify routes that ship without a limiter of their own.
 *
 * Fortify throttles login, the two-factor challenge, passkeys and email
 * verification through config('fortify.limiters'), but registration and both
 * halves of the password reset carry only a `guest` guard -- see
 * vendor/laravel/fortify/routes/routes.php, where those three routes are built
 * with no `throttle` middleware and no config key to add one. Republishing that
 * route file to add it would fork a vendor file that Fortify upgrades edit.
 *
 * So the limiter is applied here instead, the same way
 * EnsureRegistrationIsEnabled gates sign-up: appended to the `web` group and
 * self-scoped by ROUTE NAME, so it covers the routes wherever Fortify chooses
 * to register them and survives an upgrade that renames the URIs
 * (RoutePath::for() lets an application move them).
 *
 * Each route delegates to Laravel's own ThrottleRequests with a NAMED limiter,
 * rather than counting hits here: named limiters resolve through the same code
 * path as `throttle:login`, so the 429 response, the Retry-After and
 * X-RateLimit-* headers, and the cache keys all behave exactly as they do on
 * the routes Fortify throttles itself. The limiters are defined beside the
 * existing ones in App\Providers\FortifyServiceProvider.
 *
 * Why these three matter:
 *
 *   register     -- an open sign-up endpoint is a free way to fill the users
 *                   table and to make the application send mail on demand.
 *   password.email  -- the reset form reveals nothing per request, but it sends
 *                   an email for every known address, so it is the cheapest way
 *                   to pump a mailbox and burn the sender reputation the rest of
 *                   the application depends on.
 *   password.update -- the token is the secret here, and without a limit it can
 *                   be guessed at the speed the server will answer.
 */
class ThrottleSensitiveAuthRequests
{
    /**
     * The named rate limiter guarding each route, keyed by route name.
     *
     * A route absent from this list is untouched, so adding coverage is an
     * entry here plus a matching RateLimiter::for() in FortifyServiceProvider.
     *
     * @var array<string, string>
     */
    private const array LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'password-reset-link',
        'password.update' => 'password-reset',
    ];

    public function __construct(private ThrottleRequests $throttle) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::LIMITERS as $routeName => $limiter) {
            if (! $request->routeIs($routeName)) {
                continue;
            }

            // An already-authenticated request is passed through UNCOUNTED.
            //
            // This middleware runs from the `web` group, which is before the
            // route-level `guest` middleware Fortify puts on all three of these
            // routes. A signed-in user posting here is therefore turned away by
            // RedirectIfAuthenticated without ever reaching the controller: no
            // account is created, no reset mail is sent, no token is guessed.
            //
            // Counting those requests would let a signed-in user -- or a stale
            // tab retrying in the background -- exhaust an IP-keyed bucket that
            // GUESTS share. On a shared address (an office, a NAT, a mobile
            // carrier) the next real visitor is then refused a password reset by
            // requests that could never have done any harm. Skipping is safe
            // precisely because the guest middleware behind this one rejects
            // them before any work happens.
            if ($request->user() !== null) {
                return $next($request);
            }

            return $this->throttle->handle($request, $next, $limiter);
        }

        return $next($request);
    }
}
