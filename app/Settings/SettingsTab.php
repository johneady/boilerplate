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
 * Diagnostics is the exception: it claims no keys and edits nothing, rendering
 * a read-only report of the configuration the other tabs cannot express. It
 * lives here anyway so it appears alongside the settings it audits rather than
 * behind a separate navigation entry.
 */
enum SettingsTab: string
{
    case BusinessDetails = 'business_details';

    case SeoBrand = 'seo_brand';

    case Registration = 'registration';

    case Mail = 'mail';

    case LocaleTime = 'locale_time';

    case Diagnostics = 'diagnostics';

    /**
     * The heading shown on the tab in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::BusinessDetails => 'Business details',
            self::SeoBrand => 'SEO & brand',
            self::Registration => 'Registration',
            self::Mail => 'Email',
            self::LocaleTime => 'Locale & time',
            self::Diagnostics => 'Diagnostics',
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
            self::Diagnostics => Heroicon::OutlinedShieldCheck,
        };
    }
}
