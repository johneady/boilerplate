<?php

namespace App\Http\Middleware;

use App\Payments\PaymentManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer 404 on every payment route while payments are switched off.
 *
 * The routes stay registered -- a setting cannot decide route registration
 * under the route cache, and route('payments.pay') must keep resolving -- and
 * this turns them away at runtime, the same way EnsureRegistrationIsEnabled
 * handles sign-up. A 404 rather than a 403, so an installation that never
 * took payments looks exactly like one without the module.
 */
class EnsurePaymentsEnabled
{
    public function __construct(private readonly PaymentManager $payments) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->payments->enabled(), 404);

        return $next($request);
    }
}
