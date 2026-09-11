<?php

namespace App\Providers;

use App\Auth\DevLoginAccounts;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewContract;
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
        View::composer('partials.dev-login-links', function (ViewContract $view): void {
            $view->with('devUsers', app(DevLoginAccounts::class)->all());
        });

        // The footer's contact details are composed onto the component alone:
        // like the global business name below, that keeps the settings table
        // unread on requests that render no view, and unrendered views cost
        // nothing.
        View::composer('components.business-footer', function (ViewContract $view): void {
            $settings = app(Settings::class);

            $view->with('businessAddress', $settings->string(SettingKey::BusinessAddress))
                ->with('businessPhone', $settings->string(SettingKey::BusinessPhone))
                ->with('businessEmail', $settings->string(SettingKey::BusinessEmail));
        });

        // Composed rather than shared: View::share() would resolve the settings
        // service (and so query the table) while booting every request,
        // including those that render no view at all, such as API and Livewire
        // update responses.
        View::composer('*', function (ViewContract $view): void {
            $view->with('businessName', app(Settings::class)->businessName());
        });
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

        // Guarded everywhere except the environments that own a throwaway
        // database. A deployed staging instance is a real database with real
        // data on it, so keying this on isProduction() alone would let
        // migrate:fresh and db:wipe run there the moment APP_ENV is
        // overridden away from 'production'.
        DB::prohibitDestructiveCommands(
            ! app()->environment(['local', 'testing']),
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
