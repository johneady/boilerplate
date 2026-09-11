<?php

namespace App\Filament\Clusters\Account\Pages;

use App\Filament\Clusters\Account\AccountCluster;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The panel's view of the security settings.
 *
 * Hosts App\Livewire\Settings\Security. The password-confirmation gate that
 * routes/settings.php puts on /settings/security is reapplied here via
 * $routeMiddleware -- without it this page would be a second, ungated way to
 * reach 2FA disable and passkey deletion.
 */
class Security extends Page
{
    protected string $view = 'filament.clusters.account.pages.security';

    protected static ?string $cluster = AccountCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $title = 'Security';

    protected static ?int $navigationSort = 2;

    /**
     * @var string|array<int, string>
     */
    protected static string|array $routeMiddleware = ['password.confirm'];
}
