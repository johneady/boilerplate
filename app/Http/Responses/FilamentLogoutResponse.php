<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;

class FilamentLogoutResponse implements LogoutResponseContract
{
    /**
     * Send the user home after logging out of the admin panel.
     *
     * Filament's default returns to the panel's own login page, which this
     * application deliberately does not register.
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect('/');
    }
}
