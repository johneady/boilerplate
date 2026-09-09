<?php

namespace App\Http\Middleware;

use App\Settings\SettingKey;
use App\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Close public sign-up when the "allow new user registrations" setting is off.
 *
 * Fortify's own Features::registration() is read at boot to decide whether the
 * routes exist at all, so it cannot answer a setting an admin flips at runtime.
 * The routes therefore stay registered -- route('register') keeps resolving, so
 * views and redirects that reference it do not break -- and this middleware
 * turns them into a 404 while the setting is off, matching what a visitor would
 * see if sign-up had never been offered.
 *
 * Registered across the web group and scoped by route name here, so both the
 * form and its POST are covered without depending on Fortify's route file.
 */
class EnsureRegistrationIsEnabled
{
    public function __construct(private Settings $settings) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register', 'register.store')) {
            return $next($request);
        }

        abort_unless($this->settings->boolean(SettingKey::AllowRegistration), 404);

        return $next($request);
    }
}
