<?php

namespace App\Filament\Clusters\Account;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * The signed-in user's own account settings, reached from the user menu.
 *
 * The cluster exists for its sub-navigation: it gives Profile, Security and
 * Appearance a Filament-styled switcher, so a user editing their profile stays
 * inside the panel instead of being dropped onto the Flux-chromed /settings
 * pages. It is deliberately kept out of the sidebar -- these are personal
 * settings reached from the user menu, not another admin section.
 */
class AccountCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $clusterBreadcrumb = 'Account';
}
