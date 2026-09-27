<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Check a password sign-in's credentials, refusing a deactivated account.
 *
 * Registered as Fortify's authenticateUsing callback, which both halves of
 * the login pipeline call: RedirectIfTwoFactorAuthenticatable to decide on the
 * challenge, and AttemptToAuthenticate to log in. Refusing here means a
 * deactivated account is never logged in at all -- no successful sign-in in
 * the audit trail, no cleared throttle -- where EnsureAccountIsActive alone
 * would sign it in and out again on the next request.
 *
 * Everything else mirrors Fortify's default path, so only deactivation
 * changes:
 *
 * - A failed attempt is handled HERE rather than by returning null. Fortify's
 *   null branch fires Failed with no user, which would cost the audit trail
 *   its "account exists" distinction; the default path names the user, as
 *   this does, then counts the attempt and shows auth.failed.
 * - The whole check runs in a timebox, like SessionGuard::attempt(), so an
 *   unknown address and a wrong password take equally long.
 * - A valid password is rehashed when the hashing configuration has moved on.
 *
 * The deactivation check comes only after the password is validated, so it
 * tells nobody without the password whether an account is deactivated.
 */
class AuthenticateUser
{
    public function __construct(private LoginRateLimiter $limiter) {}

    /**
     * @throws ValidationException when the credentials are wrong or the account is deactivated
     */
    public function __invoke(Request $request): User
    {
        $guard = config('fortify.guard');
        $provider = Auth::guard($guard)->getProvider();

        $credentials = [
            Fortify::username() => $request->input(Fortify::username()),
            'password' => $request->input('password'),
        ];

        $user = (new Timebox)->call(function (Timebox $timebox) use ($request, $guard, $provider, $credentials): User {
            $user = $provider->retrieveByCredentials($credentials);

            if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
                event(new Failed($guard, $user, $credentials));

                $this->limiter->increment($request);

                throw ValidationException::withMessages([
                    Fortify::username() => [trans('auth.failed')],
                ]);
            }

            $timebox->returnEarly();

            return $user;
        }, (int) config('auth.timebox_duration', 200000));

        if ($user->isDeactivated()) {
            throw ValidationException::withMessages([
                Fortify::username() => __('This account has been deactivated.'),
            ]);
        }

        $this->rehashPasswordIfRequired($provider, $user, $credentials);

        return $user;
    }

    /**
     * Rehash the password under the current hashing settings, as the guard would.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function rehashPasswordIfRequired(UserProvider $provider, User $user, array $credentials): void
    {
        if (config('hashing.rehash_on_login', true)) {
            $provider->rehashPasswordIfRequired($user, $credentials);
        }
    }
}
