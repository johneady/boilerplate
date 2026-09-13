<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('livewire.auth.login'));
        Fortify::verifyEmailView(fn () => view('livewire.auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));
        Fortify::registerView(fn () => view('livewire.auth.register'));
        Fortify::resetPasswordView(fn () => view('livewire.auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('livewire.auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     *
     * The login, two-factor and passkey limiters below are wired up by Fortify
     * through config('fortify.limiters'). The three after them are not:
     * registration and both halves of the password reset ship with no limiter
     * and no config key to add one, so they are applied by route name in
     * App\Http\Middleware\ThrottleSensitiveAuthRequests. The limits live here
     * with the rest rather than inside that middleware, so every rate limit in
     * the application is read in one place.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });

        // Keyed on the IP alone: unlike login there is no existing account to
        // key on, and the address a request supplies is attacker-chosen, so
        // including it would let one client sidestep the limit by varying it.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by((string) $request->ip()));

        // Two limits, both enforced. The per-address one stops a single mailbox
        // being flooded with reset links from many IPs; the per-IP one stops a
        // single client walking a list of addresses to find which are
        // registered. Either alone leaves the other attack open.
        RateLimiter::for('password-reset-link', fn (Request $request) => [
            Limit::perHour(5)->by($this->throttleKey($request)),
            Limit::perHour(15)->by((string) $request->ip()),
        ]);

        // The reset token is the secret being guarded, so this is keyed on the
        // IP rather than the address: a guesser varies the token, not the email.
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perHour(10)->by((string) $request->ip()));
    }

    /**
     * Build a limiter key from the submitted username and the client address.
     *
     * Mirrors the normalisation the login limiter above applies, so "Ada@x.test"
     * and "ada@x.test" share one bucket rather than getting a fresh allowance
     * each: without the lowercasing a limit keyed on the address is trivially
     * bypassed by changing its capitalisation.
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());
    }
}
