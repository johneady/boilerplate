<?php

namespace App\Filament\Clusters\Account\Pages;

use App\Filament\Clusters\Account\AccountCluster;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The panel's view of the profile settings.
 *
 * The page is a host for App\Livewire\Settings\Profile -- the same component
 * /settings/profile renders -- so avatar uploads, the email re-verification
 * prompt and account deletion behave identically in both places and are fixed
 * in one.
 */
class Profile extends Page
{
    protected string $view = 'filament.clusters.account.pages.profile';

    protected static ?string $cluster = AccountCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $title = 'Profile';

    protected static ?int $navigationSort = 1;
}
