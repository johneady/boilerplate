<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Laravel\Fortify\Fortify;

trait ResolvesLoginRedirect
{
    /**
     * Build the redirect for a freshly authenticated user.
     *
     * Honours an intended URL captured before login, except for panel users
     * whose intended URL is merely the dashboard they were bounced off on the
     * way to logging in — sending them there would strand them outside the
     * panel.
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
     * Anyone who may use the admin panel -- administrators and staff roles
     * alike -- lands there, everyone else on the regular dashboard. Any intended URL captured before login still takes
     * precedence, since this is only ever used as the fallback.
     */
    protected function defaultRedirect(?Authenticatable $user): string
    {
        if ($this->usesAdminPanel($user)) {
            // getUrl() is nullable when the panel has no base path to build
            // from, so the dashboard fallback stays genuinely reachable.
            return $this->adminPanel()?->getUrl() ?? route('dashboard', absolute: false);
        }

        return Fortify::redirects('login');
    }

    /**
     * Determine whether a panel user's captured intended URL should be dropped.
     *
     * Only the non-panel default landing page is discarded. A deliberate deep
     * link a panel user followed before logging in is still honoured.
     */
    private function shouldDiscardIntendedUrl(?Authenticatable $user): bool
    {
        if (! $this->usesAdminPanel($user)) {
            return false;
        }

        $intended = Session::get('url.intended');

        if (! is_string($intended)) {
            return false;
        }

        return $this->normalizePath($intended) === $this->normalizePath(Fortify::redirects('login'));
    }

    /**
     * Determine whether the given user works in the admin panel.
     *
     * Asked of canAccessPanel() -- the same question Filament asks at the
     * panel's door -- rather than of is_admin, so the staff roles land where
     * they work too, and a login never redirects anyone to a panel that
     * would answer 403.
     */
    private function usesAdminPanel(?Authenticatable $user): bool
    {
        $panel = $this->adminPanel();

        return $user instanceof User && $panel !== null && $user->canAccessPanel($panel);
    }

    /**
     * The admin panel, or null when no panel is registered under that id.
     *
     * getPanels()['admin'] rather than getPanel('admin'): the latter THROWS
     * for an unregistered id instead of returning null, so a renamed or
     * removed panel would turn every staff login into a 500.
     */
    private function adminPanel(): ?Panel
    {
        return Filament::getPanels()['admin'] ?? null;
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
