<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Let a request through only for a subscriber: `subscribed` for any plan,
 * `subscribed:pro` for the plan with that key.
 *
 * Put it after `auth`. A browser without a subscription is sent to the
 * pricing page; an API client gets a 403.
 */
class EnsureUserIsSubscribed
{
    public function handle(Request $request, Closure $next, ?string $planKey = null): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->subscribed($planKey)) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403, __('An active subscription is required.'));

        return redirect()->route('payments.pricing');
    }
}
