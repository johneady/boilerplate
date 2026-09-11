<?php

namespace App\Settings;

use Filament\Support\Icons\Heroicon;

/**
 * The tabs the settings page groups its settings under.
 *
 * SettingKey::tab() assigns every key to one of these, and the page builds a
 * tab per case in declaration order -- adding a tab is a case here plus the
 * keys that claim it, with no separate list to keep in step.
 */
enum SettingsTab: string
{
    case BusinessDetails = 'business_details';

    case Registration = 'registration';

    case Mail = 'mail';

    /**
     * The heading shown on the tab in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::BusinessDetails => 'Business details',
            self::Registration => 'Registration',
            self::Mail => 'Email',
        };
    }

    /**
     * The icon shown beside the tab label in the admin panel.
     */
    public function icon(): Heroicon
    {
        return match ($this) {
            self::BusinessDetails => Heroicon::OutlinedBuildingOffice2,
            self::Registration => Heroicon::OutlinedUserPlus,
            self::Mail => Heroicon::OutlinedEnvelope,
        };
    }
}
