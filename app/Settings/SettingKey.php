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
    case BusinessName = 'business_name';

    case BusinessAddress = 'business_address';

    case BusinessPhone = 'business_phone';

    case BusinessEmail = 'business_email';

    case AllowRegistration = 'allow_registration';

    case MailMailer = 'mail_mailer';

    case MailHost = 'mail_host';

    case MailPort = 'mail_port';

    case MailUsername = 'mail_username';

    case MailPassword = 'mail_password';

    case MailEncryption = 'mail_encryption';

    case MailFromAddress = 'mail_from_address';

    case MailFromName = 'mail_from_name';

    /**
     * The settings-page tab this key is edited on.
     */
    public function tab(): SettingsTab
    {
        return match ($this) {
            self::BusinessName, self::BusinessAddress, self::BusinessPhone, self::BusinessEmail => SettingsTab::BusinessDetails,
            self::AllowRegistration => SettingsTab::Registration,
            self::MailMailer, self::MailHost, self::MailPort, self::MailUsername, self::MailPassword, self::MailEncryption, self::MailFromAddress, self::MailFromName => SettingsTab::Mail,
        };
    }

    /**
     * The value used when no row exists for this key yet.
     */
    public function default(): mixed
    {
        return match ($this) {
            self::BusinessName => config('app.name', 'Laravel'),
            self::BusinessAddress, self::BusinessPhone, self::BusinessEmail => '',
            self::AllowRegistration => false,
            self::MailMailer => 'log',
            self::MailHost, self::MailPort, self::MailUsername, self::MailPassword, self::MailEncryption, self::MailFromAddress, self::MailFromName => '',
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
            self::BusinessName, self::BusinessAddress, self::BusinessPhone, self::BusinessEmail => self::toFilledString($value, $this->default()),
            self::AllowRegistration => self::toBoolean($value),
            self::MailMailer => self::toOneOf($value, ['log', 'smtp'], 'log'),
            self::MailEncryption => self::toOneOf($value, ['', 'tls', 'ssl', 'none'], ''),
            self::MailHost, self::MailPort, self::MailUsername, self::MailPassword, self::MailFromAddress, self::MailFromName => self::toFilledString($value, ''),
        };
    }

    /**
     * Interpret a stored value as a non-empty, trimmed string.
     *
     * A row holding null, an empty string, or whitespace falls back to the
     * declared default: for the business name that keeps the brand rendering
     * rather than blank, and for the optional contact details it means an
     * unset detail reads as an empty string the footer knows to hide.
     */
    private static function toFilledString(mixed $value, string $default): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return $default;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? $default : $trimmed;
    }

    /**
     * Interpret a stored value as one of a fixed set, failing closed.
     *
     * Like toBoolean(), this guards a setting that switches behaviour: a mailer
     * row hand-edited to a typo or an unsupported driver must fall back to the
     * declared default rather than produce a mailer that cannot resolve.
     *
     * @param  array<int, string>  $allowed
     */
    private static function toOneOf(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
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
            self::BusinessName => 'Business name',
            self::BusinessAddress => 'Address',
            self::BusinessPhone => 'Phone',
            self::BusinessEmail => 'Email',
            self::AllowRegistration => 'Allow new user registrations',
            self::MailMailer => 'Mailer',
            self::MailHost => 'Host',
            self::MailPort => 'Port',
            self::MailUsername => 'Username',
            self::MailPassword => 'Password',
            self::MailEncryption => 'Encryption',
            self::MailFromAddress => 'From address',
            self::MailFromName => 'From name',
        };
    }

    /**
     * Supporting copy shown beneath this setting's field in the admin panel.
     */
    public function helperText(): string
    {
        return match ($this) {
            self::BusinessName => 'Shown in the admin panel and across the public site.',
            self::BusinessAddress => 'The postal address shown in the public site\'s footer. Leave blank to hide it.',
            self::BusinessPhone => 'The phone number shown in the public site\'s footer. Leave blank to hide it.',
            self::BusinessEmail => 'The contact address shown in the public site\'s footer. Leave blank to hide it.',
            self::AllowRegistration => 'When off, the sign-up page is unavailable and only an administrator can create accounts.',
            self::MailMailer => 'How outgoing email is delivered. "Log" writes messages to the application log; "SMTP" sends through the server below.',
            self::MailHost => 'The SMTP server to send through, e.g. smtp.fastmail.com. Required before SMTP delivery is used.',
            self::MailPort => 'The port to connect on. Leave blank for the default of 587.',
            self::MailUsername => 'The SMTP username, if the server requires authentication.',
            self::MailPassword => 'The SMTP password, if the server requires authentication.',
            self::MailEncryption => 'How the connection is secured. Leave blank for the default (TLS).',
            self::MailFromAddress => 'The address outgoing email is sent from. Leave blank to keep the deployment default.',
            self::MailFromName => 'The name outgoing email is sent from. Leave blank to use the business name.',
        };
    }
}
