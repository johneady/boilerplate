<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Laravel\Fortify\Fortify;

trait ResolvesLoginRedirect
{
    /**
     * Build the redirect for a freshly authenticated user.
     *
     * Honours an intended URL captured before login, except for admins whose
     * intended URL is merely the dashboard they were bounced off on the way
     * to logging in — sending them there would strand them outside the panel.
     */
    protected function loginRedirect(?Authenticatable $user): RedirectResponse
    {
        if ($this->shouldDiscardIntendedUrl($user)) {
            Session::forget('url.intended');
        }

        return redirect()->intended($this->defaultRedirect($user));
    }

    /**
     * Resolve the default landing page for a freshly authenticated user.
     *
     * Admins land on the Filament admin panel, everyone else on the regular
     * dashboard. Any intended URL captured before login still takes
     * precedence, since this is only ever used as the fallback.
     */
    protected function defaultRedirect(?Authenticatable $user): string
    {
        if ($this->isAdmin($user)) {
            return Filament::getPanel('admin')->getUrl();
        }

        return Fortify::redirects('login');
    }

    /**
     * Determine whether an admin's captured intended URL should be dropped.
     *
     * Only the non-admin default landing page is discarded. A deliberate deep
     * link an admin followed before logging in is still honoured.
     */
    private function shouldDiscardIntendedUrl(?Authenticatable $user): bool
    {
        if (! $this->isAdmin($user)) {
            return false;
        }

        $intended = Session::get('url.intended');

        if (! is_string($intended)) {
            return false;
        }

        return $this->normalizePath($intended) === $this->normalizePath(Fortify::redirects('login'));
    }

    /**
     * Determine whether the given user may administer the application.
     */
    private function isAdmin(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->is_admin;
    }

    /**
     * Reduce a URL to a comparable path, ignoring host and trailing slashes.
     */
    private function normalizePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return '/'.trim(is_string($path) ? $path : '', '/');
    }
}
