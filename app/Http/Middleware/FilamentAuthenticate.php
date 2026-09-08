<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticateMiddleware;

class FilamentAuthenticate extends FilamentAuthenticateMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, ...$guards): mixed
    {
        $this->authenticate($request, $guards);

        $user = $request->user();

        if (! $user || ! $user->is_admin) {
            abort(403, 'You do not have permission to access the admin panel.');
        }

        return $next($request);
    }

    /**
     * Send unauthenticated visitors to the application's single login page.
     *
     * The panel deliberately has no login page of its own, so without this
     * guests would hit a missing route instead of Fortify's login.
     */
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
