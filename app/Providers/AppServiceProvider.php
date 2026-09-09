<?php

namespace App\Providers;

use App\Settings\SettingKey;
use App\Settings\Settings;
use App\View\Composers\DevLoginLinksComposer;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped rather than a singleton: within a request the settings table
        // is still read at most once however many settings are consulted, but
        // a queue worker -- a long-lived process where a singleton would live
        // for the life of the worker -- drops it between jobs and re-reads,
        // rather than acting on a value an administrator has since changed.
        $this->app->scoped(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureViewComposers();
        $this->configureBladeDirectives();
        $this->configurePersistentMiddleware();
    }

    /**
     * Register Blade conditions backed by application settings.
     *
     * Views ask @registrationEnabled rather than reaching for the settings
     * service themselves, so the sign-up links disappear alongside the routes
     * that the EnsureRegistrationIsEnabled middleware closes.
     */
    protected function configureBladeDirectives(): void
    {
        Blade::if('registrationEnabled', fn (): bool => app(Settings::class)
            ->boolean(SettingKey::AllowRegistration));
    }

    /**
     * Bind data needed by views that cannot resolve it themselves.
     */
    protected function configureViewComposers(): void
    {
        View::composer('partials.dev-login-links', DevLoginLinksComposer::class);
    }

    /**
     * Keep route middleware enforcing in-request security re-checked on
     * Livewire update requests.
     *
     * Livewire only re-runs middleware from its persistent list on subsequent
     * updates, so a route's `password.confirm` gate otherwise protects the
     * page render alone: actions on a stale snapshot could disable two-factor
     * authentication or delete passkeys without a recent confirmation.
     */
    protected function configurePersistentMiddleware(): void
    {
        Livewire::addPersistentMiddleware(RequirePassword::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Strict everywhere except the two environments where weak passwords
        // are convenient: staging and any other non-production-like host must
        // enforce the full policy, not only production.
        Password::defaults(fn (): ?Password => app()->environment('local', 'testing')
            ? null
            : Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised(),
        );
    }
}
