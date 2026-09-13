<?php

namespace App\Settings;

use DateTimeZone;

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
    /**
     * The date formats an administrator may choose between, keyed by the PHP
     * format string with the note shown beside its rendered example. The
     * first is the default.
     *
     * @var array<string, string>
     */
    public const DATE_FORMATS = [
        'j M Y' => 'Default',
        'j F Y' => 'Full month name',
        'd/m/Y' => 'Day first — UK, Australia',
        'm/d/Y' => 'Month first — United States',
        'd.m.Y' => 'Dotted — Germany, central Europe',
        'd-m-Y' => 'Dashed — Netherlands, Portugal',
        'Y-m-d' => 'ISO 8601 — Canada, sortable',
    ];

    /**
     * The time formats an administrator may choose between, keyed by the PHP
     * format string with the note shown beside its rendered example.
     *
     * Order is presentation only -- the default is DEFAULT_TIME_FORMAT below,
     * not the first entry -- so these can be reordered without changing what an
     * unsaved or invalid row reads back as.
     *
     * @var array<string, string>
     */
    public const TIME_FORMATS = [
        'g:i a' => '12-hour, lowercase am/pm — Default',
        'g:i A' => '12-hour, uppercase AM/PM',
        'H:i' => '24-hour',
    ];

    /**
     * The time format used until an administrator chooses one.
     *
     * Named explicitly rather than taken from the first TIME_FORMATS entry: the
     * default is a decision, and deriving it from array order means reordering
     * the options for presentation silently changes the format every instance
     * that has not saved this setting renders. The same value answers an unsaved
     * row and a row holding a format that is not offered, so the two agree.
     */
    public const string DEFAULT_TIME_FORMAT = 'g:i a';

    /**
     * The locales an administrator may choose between, keyed by code with the
     * English name shown in the panel.
     *
     * Curated rather than exhaustive: every code here is one Carbon ships
     * translations for, so month and day names and relative times localise
     * the moment it is chosen. Add a code here only once its translations
     * actually exist, or the panel will offer locales that change nothing.
     *
     * @var array<string, string>
     */
    public const LOCALES = [
        'en' => 'English',
        'en_GB' => 'English (British)',
        'en_AU' => 'English (Australian)',
        'en_CA' => 'English (Canadian)',
        'fr' => 'French',
        'de' => 'German',
        'es' => 'Spanish',
        'it' => 'Italian',
        'nl' => 'Dutch',
        'pt' => 'Portuguese',
        'pt_BR' => 'Portuguese (Brazil)',
        'da' => 'Danish',
        'sv' => 'Swedish',
        'nb' => 'Norwegian',
        'pl' => 'Polish',
        'tr' => 'Turkish',
        'ru' => 'Russian',
        'uk' => 'Ukrainian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'zh_CN' => 'Chinese (Simplified)',
        'ar' => 'Arabic',
        'hi' => 'Hindi',
        'id' => 'Indonesian',
    ];

    case BusinessName = 'business_name';

    case BusinessAddress = 'business_address';

    case BusinessPhone = 'business_phone';

    case BusinessEmail = 'business_email';

    case SeoTitle = 'seo_title';

    case SeoDescription = 'seo_description';

    case AllowSearchIndexing = 'allow_search_indexing';

    /*
     * The logo's UPLOAD CONTROL, not its storage.
     *
     * Nothing writes a value against this key any more -- the logo is an
     * ownerless App\Models\Media row in the Logo collection, read through
     * Settings::logoMedia(). The case stays because the admin panel builds the
     * Brand tab's file field and its placement from the enum, so removing it
     * would take the upload button with it. Reading string(SettingKey::Logo)
     * will always return the empty default; use Settings::logoUrl() instead.
     */
    case Logo = 'logo';

    case AllowRegistration = 'allow_registration';

    case MailMailer = 'mail_mailer';

    case MailHost = 'mail_host';

    case MailPort = 'mail_port';

    case MailUsername = 'mail_username';

    case MailPassword = 'mail_password';

    case MailEncryption = 'mail_encryption';

    case MailFromAddress = 'mail_from_address';

    case MailFromName = 'mail_from_name';

    case OpsAlertEmail = 'ops_alert_email';

    case Timezone = 'timezone';

    case Locale = 'locale';

    case DateFormat = 'date_format';

    case TimeFormat = 'time_format';

    /**
     * The settings-page tab this key is edited on.
     */
    public function tab(): SettingsTab
    {
        return match ($this) {
            self::BusinessName, self::BusinessAddress, self::BusinessPhone, self::BusinessEmail => SettingsTab::BusinessDetails,
            self::SeoTitle, self::SeoDescription, self::AllowSearchIndexing, self::Logo => SettingsTab::SeoBrand,
            self::AllowRegistration => SettingsTab::Registration,
            self::MailMailer, self::MailHost, self::MailPort, self::MailUsername, self::MailPassword, self::MailEncryption, self::MailFromAddress, self::MailFromName, self::OpsAlertEmail => SettingsTab::Mail,
            self::Timezone, self::Locale, self::DateFormat, self::TimeFormat => SettingsTab::LocaleTime,
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
            self::SeoTitle, self::SeoDescription, self::Logo => '',
            self::AllowSearchIndexing => true,
            self::AllowRegistration => false,
            self::MailMailer => 'log',
            self::MailHost, self::MailPort, self::MailUsername, self::MailPassword, self::MailEncryption, self::MailFromAddress, self::MailFromName, self::OpsAlertEmail => '',
            // UTC is the storage timezone this application pins in config, so
            // the display setting defaults to it too: reading an unsaved row
            // and reading a hand-edited one agree.
            self::Timezone => 'UTC',
            self::Locale => 'en',
            self::DateFormat => array_key_first(self::DATE_FORMATS),
            self::TimeFormat => self::DEFAULT_TIME_FORMAT,
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
            self::SeoTitle, self::SeoDescription, self::Logo => self::toFilledString($value, ''),
            self::AllowSearchIndexing => self::toBoolean($value),
            self::AllowRegistration => self::toBoolean($value),
            self::MailMailer => self::toOneOf($value, ['log', 'smtp'], 'log'),
            self::MailEncryption => self::toOneOf($value, ['', 'tls', 'ssl', 'none'], ''),
            self::MailHost, self::MailPort, self::MailUsername, self::MailPassword, self::MailFromAddress, self::MailFromName, self::OpsAlertEmail => self::toFilledString($value, ''),
            self::Timezone => self::toTimezone($value),
            self::Locale => self::toOneOf($value, array_keys(self::LOCALES), 'en'),
            self::DateFormat => self::toOneOf($value, array_keys(self::DATE_FORMATS), array_key_first(self::DATE_FORMATS)),
            self::TimeFormat => self::toOneOf($value, array_keys(self::TIME_FORMATS), self::DEFAULT_TIME_FORMAT),
        };
    }

    /**
     * Interpret a stored value as a timezone identifier, failing closed.
     *
     * A timezone pervades every date the application renders, so a row
     * hand-edited to a typo must fall back to UTC rather than hand Carbon
     * an identifier it throws on at render time.
     */
    private static function toTimezone(mixed $value): string
    {
        return is_string($value) && in_array($value, DateTimeZone::listIdentifiers(), true)
            ? $value
            : 'UTC';
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
     * Whether this setting holds a credential that must never be recorded.
     *
     * Read by App\Settings\Settings when writing the audit trail: a secret
     * setting records that it changed, never what it changed to or from. The
     * audit table is readable by every administrator and outlives the change
     * by the configured retention, so a password copied into it is a password
     * stored twice, in the less guarded of the two places.
     *
     * A match rather than a list, so a new case must be classified here and
     * PHPStan reports the omission instead of defaulting it to "not secret".
     */
    public function isSecret(): bool
    {
        return match ($this) {
            self::MailPassword => true,
            self::BusinessName, self::BusinessAddress, self::BusinessPhone, self::BusinessEmail,
            self::SeoTitle, self::SeoDescription, self::AllowSearchIndexing, self::Logo,
            self::AllowRegistration, self::MailMailer, self::MailHost, self::MailPort,
            self::MailUsername, self::MailEncryption, self::MailFromAddress, self::MailFromName,
            self::OpsAlertEmail, self::Timezone, self::Locale, self::DateFormat,
            self::TimeFormat => false,
        };
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
            self::SeoTitle => 'Default page title',
            self::SeoDescription => 'Meta description',
            self::AllowSearchIndexing => 'Allow search engines to index the site',
            self::Logo => 'Logo',
            self::AllowRegistration => 'Allow new user registrations',
            self::MailMailer => 'Mailer',
            self::MailHost => 'Host',
            self::MailPort => 'Port',
            self::MailUsername => 'Username',
            self::MailPassword => 'Password',
            self::MailEncryption => 'Encryption',
            self::MailFromAddress => 'From address',
            self::MailFromName => 'From name',
            self::OpsAlertEmail => 'Failure alert address',
            self::Timezone => 'Display timezone',
            self::Locale => 'Locale',
            self::DateFormat => 'Date format',
            self::TimeFormat => 'Time format',
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
            self::SeoTitle => 'Used as the title of pages without their own, and as the headline of social link previews. Leave blank to use the business name.',
            self::SeoDescription => 'A sentence or two summarising the site for search results and link previews. Leave blank to omit the tag.',
            self::AllowSearchIndexing => 'When off, every page asks search engines not to index it or follow its links. Turn off while a site is under development.',
            self::Logo => 'Shown as the brand mark across the site and in the admin panel, and re-encoded into the favicon, Apple touch icon and social sharing image. Square artwork works best.',
            self::AllowRegistration => 'When off, the sign-up page is unavailable and only an administrator can create accounts.',
            self::MailMailer => 'How outgoing email is delivered. "Log" writes messages to the application log; "SMTP" sends through the server below. A from address must be set on the Email tab before SMTP can be chosen.',
            self::MailHost => 'The SMTP server to send through, e.g. smtp.fastmail.com. Required before SMTP delivery is used.',
            self::MailPort => 'The port to connect on. Leave blank for the default of 587.',
            self::MailUsername => 'The SMTP username, if the server requires authentication.',
            self::MailPassword => 'The SMTP password, if the server requires authentication.',
            self::MailEncryption => 'How the connection is secured. Leave blank for the default (TLS).',
            self::MailFromAddress => 'The address outgoing email is sent from. Required when the mailer is SMTP; on the log mailer, leave blank to keep the address the deployment configured.',
            self::MailFromName => 'The name outgoing email is sent from. Leave blank to use the business name.',
            self::OpsAlertEmail => 'Where to email a warning when a queued background job fails. Leave blank to send no alerts; failures are written to the application log either way.',
            self::Timezone => 'Dates and times are stored as UTC and shown converted to this timezone across the site. Changing it never rewrites stored data, so it is safe to change at any time.',
            self::Locale => 'Localises month and day names and relative times such as "2 hours ago". Interface text stays in English until translation files are added to the application.',
            self::DateFormat => 'How dates are shown. Each option names the convention it belongs to; month and day names follow the locale chosen above.',
            self::TimeFormat => 'Shown beside the date wherever a full date and time appears.',
        };
    }
}
