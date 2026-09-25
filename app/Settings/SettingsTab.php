<?php

namespace App\Settings;

use Filament\Support\Icons\Heroicon;

/**
 * The tabs the settings page groups its settings under.
 *
 * SettingKey::tab() assigns every key to one of these, and the page builds a
 * tab per case in declaration order -- adding a tab is a case here plus the
 * keys that claim it, with no separate list to keep in step.
 *
 * Diagnostics and Server are the exceptions: they claim no keys and edit
 * nothing, rendering read-only reports -- one auditing the configuration,
 * one describing the machine -- so they live here anyway to appear
 * alongside the settings they sit beside rather than behind separate
 * navigation entries.
 */
enum SettingsTab: string
{
    case BusinessDetails = 'business_details';

    case SeoBrand = 'seo_brand';

    case Registration = 'registration';

    case Mail = 'mail';

    case LocaleTime = 'locale_time';

    case Payments = 'payments';

    case Diagnostics = 'diagnostics';

    case Server = 'server';

    /**
     * The heading shown on the tab in the admin panel.
     *
     * Deliberately single words where the group allows it: seven tabs share
     * one strip, and a compound label on each is what made it wrap.
     */
    public function label(): string
    {
        return match ($this) {
            self::BusinessDetails => 'Business',
            self::SeoBrand => 'Brand',
            self::Registration => 'Registration',
            self::Mail => 'Email',
            self::LocaleTime => 'Locale',
            self::Payments => 'Payments',
            self::Diagnostics => 'Diagnostics',
            self::Server => 'Server',
        };
    }

    /**
     * The icon shown beside the tab label in the admin panel.
     */
    public function icon(): Heroicon
    {
        return match ($this) {
            self::BusinessDetails => Heroicon::OutlinedBuildingOffice2,
            self::SeoBrand => Heroicon::OutlinedGlobeAlt,
            self::Registration => Heroicon::OutlinedUserPlus,
            self::Mail => Heroicon::OutlinedEnvelope,
            self::LocaleTime => Heroicon::OutlinedClock,
            self::Payments => Heroicon::OutlinedCreditCard,
            self::Diagnostics => Heroicon::OutlinedShieldCheck,
            self::Server => Heroicon::OutlinedServer,
        };
    }
}
