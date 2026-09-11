<?php

namespace App\Providers\Filament;

use App\Filament\Clusters\Account\Pages\Profile;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Responses\FilamentLogoutResponse;
use App\Settings\Settings;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Register panel services.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(LogoutResponseContract::class, FilamentLogoutResponse::class);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // A closure, not a resolved string: the panel is configured once at
            // boot, so reading the setting here directly would pin the brand to
            // whatever was stored then and ignore later edits.
            ->brandName(fn (): string => app(Settings::class)->businessName())
            // No resource is worth a topbar search field on this panel yet.
            ->globalSearch(false)
            ->colors([
                'primary' => Color::Amber,
            ])
            // Filament caps page content at 7xl (80rem) by default, which leaves
            // a wide gutter between the sidebar and the content on large screens.
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // The profile link opens the Account cluster inside the panel
            // rather than /settings/profile, which would drop the user out of
            // Filament and into the Flux-chromed layout.
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action
                    ->label('Account settings')
                    ->url(fn (): string => Profile::getUrl()),
            ])
            // The Account cluster hosts Flux-built Livewire components, whose
            // modals, dropdowns and file pickers need Flux's own runtime. The
            // panel layout is not the Flux app layout, so it would otherwise
            // ship none of it and those controls would render inert.
            //
            // Deliberately NOT @fluxAppearance: it is a second dark-mode system
            // keyed on flux.appearance, and it runs after Filament's. With no
            // Flux key set it resolves to 'system' and strips html.dark, so it
            // would undo Filament's own theme switcher on every page load.
            // Dark mode in the panel belongs to Filament alone.
            // The toast group belongs with the scripts: the hosted components
            // report success through Flux::toast(), and Flux drops a toast on
            // the floor when the page renders no group to put it in -- so a
            // saved password would confirm nothing.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<flux:toast.group><flux:toast /></flux:toast.group>@fluxScripts'),
            )
            ->navigationItems([
                NavigationItem::make('Return to website')
                    ->url('/')
                    ->icon('heroicon-o-globe-alt')
                    ->sort(99),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ]);
    }
}
