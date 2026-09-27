<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign out a deactivated account on its next request.
 *
 * Checked here, on every request in the web group, rather than in the login
 * pipeline: an account is reached by a password, a passkey, a "remember me"
 * cookie or the local dev login, and only this one place sees all of them.
 * User::deactivate() already purges database sessions and rotates the remember
 * token; this is what catches a session that outlived that, and the sign-in
 * that follows a deactivation.
 *
 * The admin panel runs its own middleware stack, not the web group, so it is
 * guarded separately by User::canAccessPanel().
 */
class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isDeactivated()) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('This account has been deactivated.'));
    }
}
