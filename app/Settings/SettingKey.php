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
     * Values survive a JSON round trip through the database, so this mainly
     * guards against rows written before a key's type was settled, or by hand.
     */
    public function cast(mixed $value): mixed
    {
        return match ($this) {
            self::AllowRegistration => self::toBoolean($value),
        };
    }

    /**
     * Interpret a stored value as a boolean, failing closed.
     *
     * A plain `(bool)` cast is wrong for a setting that gates access: the
     * strings "false" and "off" are both truthy in PHP, so a row hand-edited
     * in a database client would silently turn a guarded feature on. Anything
     * that is not recognisably true is therefore treated as false.
     */
    private static function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
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
