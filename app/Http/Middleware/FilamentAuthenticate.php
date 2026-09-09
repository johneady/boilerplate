<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticateMiddleware;

class FilamentAuthenticate extends FilamentAuthenticateMiddleware
{
    /**
     * Authorization happens entirely in the parent: it redirects
     * unauthenticated visitors to redirectTo() below, and aborts with 403
     * when User::canAccessPanel() -- the single source of truth for panel
     * access -- refuses the user.
     *
     * Send unauthenticated visitors to the application's single login page;
     * the panel deliberately has no login page of its own, so without this
     * guests would hit a missing route instead of Fortify's login.
     */
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
