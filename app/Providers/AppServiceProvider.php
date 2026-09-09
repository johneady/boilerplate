<?php

namespace App\Providers;

use App\Settings\SettingKey;
use App\Settings\Settings;
use App\View\Composers\DevLoginLinksComposer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // A singleton so the settings table is read at most once per request,
        // however many settings are consulted.
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureViewComposers();
        $this->configureBladeDirectives();
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
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
