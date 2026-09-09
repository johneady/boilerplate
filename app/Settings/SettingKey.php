<?php

namespace App\Settings;

/**
 * Every application setting stored in the `settings` table.
 *
 * The table itself is key/value, so adding a setting never needs a migration --
 * add a case here instead. Each case declares its own default and type, which
 * keeps that knowledge in one place rather than at every call site: a row that
 * has never been saved still reads back as a correctly typed value.
 */
enum SettingKey: string
{
    case AllowRegistration = 'allow_registration';

    /**
     * The value used when no row exists for this key yet.
     */
    public function default(): mixed
    {
        return match ($this) {
            self::AllowRegistration => false,
        };
    }

    /**
     * Coerce a stored value into the type this setting is declared to hold.
     *
     * Values survive a JSON round trip through the database, so this is mostly
     * a guard against rows written before a key's type was settled, or by hand.
     */
    public function cast(mixed $value): mixed
    {
        return match ($this) {
            self::AllowRegistration => (bool) $value,
        };
    }

    /**
     * The label shown for this setting in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::AllowRegistration => 'Allow new user registrations',
        };
    }

    /**
     * Supporting copy shown beneath this setting's field in the admin panel.
     */
    public function helperText(): string
    {
        return match ($this) {
            self::AllowRegistration => 'When off, the sign-up page is unavailable and only an administrator can create accounts.',
        };
    }
}
