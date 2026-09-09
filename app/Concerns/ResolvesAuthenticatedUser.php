<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

trait ResolvesAuthenticatedUser
{
    /**
     * The authenticated user, non-null.
     *
     * Every component using this concern is rendered from an auth-gated
     * route, so the exception is unreachable in practice; it exists so the
     * accessor has a non-nullable type instead of sprinkling null-safety
     * through the component.
     */
    protected function authenticatedUser(): User
    {
        return Auth::user()
            ?? throw new AuthenticationException('Unauthenticated.');
    }
}
