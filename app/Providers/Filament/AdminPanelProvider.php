<?php

namespace App\Providers\Filament;

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use App\Auth\Permission;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
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
use Illuminate\Contracts\Support\Htmlable;
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
            // The lockup that brandName labels: the mark before the name, in
            // the sidebar header and the mobile topbar. A View is lazy, so the
            // composers binding $businessName and $logoMarkUrl run at render
            // time -- the same property the brandName closure above preserves.
            ->brandLogo(fn (): Htmlable => view('filament.brand-logo'))
            // The user-menu fallback when no photo is uploaded: the gradient
            // initials avatar, not Filament's default flat circle -- which is
            // fetched from ui-avatars.com and so leaks the user's name to a
            // third party on every panel page view.
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // No resource is worth a topbar search field on this panel yet.
            ->globalSearch(false)
            // amber and zinc back the role badges (App\Auth\Role::color()).
            // Filament only emits a colour's CSS custom properties for colours
            // registered on the panel, so a badge naming an unregistered one
            // renders with the fi-color-* class applied but no colour behind
            // it -- visibly flat, with nothing in the markup to show why.
            ->colors([
                'primary' => Color::Blue,
                'amber' => Color::Amber,
                'zinc' => Color::Zinc,
            ])
            // Filament caps page content at 7xl (80rem) by default, which leaves
            // a wide gutter between the sidebar and the content on large screens.
            ->maxContentWidth(Width::Full)
            // The default 20rem sidebar is wider than this panel's short menu
            // needs; 16rem is the same menu 20% narrower, with more room left
            // for the full-width content beside it.
            ->sidebarWidth('16rem')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // The log viewer ships its own /admin/logs page; it is gated by the
            // logs.view permission rather than panel access alone, because
            // stack traces and mail bodies are operational data that not every
            // panel-worthy role should read. Clearing stays disabled via the
            // package default (enable_delete=false).
            ->plugins([
                FilamentLogViewer::make()
                    ->authorize(fn (): bool => auth()->user()?->hasPermission(Permission::ViewLogs) ?? false)
                    // Pinned just under Settings (sort 90) rather than left on
                    // the package's 9999 default, so the two System items keep
                    // their order even if that default ever changes.
                    ->navigationSort(91),
            ])
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
