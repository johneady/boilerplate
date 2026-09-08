<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Fortify;

trait ResolvesLoginRedirect
{
    /**
     * Resolve the default landing page for a freshly authenticated user.
     *
     * Admins land on the Filament admin panel, everyone else on the regular
     * dashboard. Any intended URL captured before login still takes
     * precedence, since this is only ever used as the fallback.
     */
    protected function defaultRedirect(?Authenticatable $user): string
    {
        if ($user instanceof User && $user->is_admin) {
            return Filament::getPanel('admin')->getUrl();
        }

        return Fortify::redirects('login');
    }
}
