<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language the visitor picked in the header's switcher.
 *
 * The choice lives in the session (set by the `locale` route). A value that is
 * not one of config('voltiva.locales') is ignored rather than trusted, so a
 * tampered session cannot point the translator at an arbitrary file.
 *
 * Appended to the `web` group, which Livewire's update endpoint also runs
 * through -- so a form re-rendered after validation stays in the visitor's
 * language rather than falling back to English mid-form.
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get('locale') : null;

        if (is_string($locale) && array_key_exists($locale, (array) config('voltiva.locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
