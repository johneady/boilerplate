<?php

namespace App\Audit;

/**
 * The kind of thing an audit log entry records.
 *
 * A closed set rather than a free-text column: the values are filtered on in
 * the panel and asserted on in tests, so a typo at a write site must be a
 * static error rather than a row that quietly never matches a filter.
 *
 * Backed values are namespaced strings ("auth.login") for the same reason
 * App\Auth\Permission's are -- they read clearly in a database column and an
 * unrelated event cannot collide with them.
 *
 * Like App\Auth\Role and App\Auth\Permission, this returns plain English from
 * label() and must keep doing so: it is covered by a unit test, which has no
 * container, so __() here dies with "Target class [translator] does not
 * exist". Translate at the point of display. See .ai/rules/i18n.md.
 */
enum AuditEvent: string
{
    case Created = 'model.created';

    case Updated = 'model.updated';

    case Deleted = 'model.deleted';

    case Login = 'auth.login';

    case Logout = 'auth.logout';

    case LoginFailed = 'auth.login_failed';

    case PasswordReset = 'auth.password_reset';

    case TwoFactorEnabled = 'auth.two_factor_enabled';

    case TwoFactorDisabled = 'auth.two_factor_disabled';

    case SettingsUpdated = 'settings.updated';

    /**
     * The label shown wherever this event is displayed to a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Login => 'Signed in',
            self::Logout => 'Signed out',
            self::LoginFailed => 'Failed sign-in',
            self::PasswordReset => 'Password reset',
            self::TwoFactorEnabled => 'Two-factor enabled',
            self::TwoFactorDisabled => 'Two-factor disabled',
            self::SettingsUpdated => 'Settings updated',
        };
    }

    /**
     * The colour this event is badged with in the admin panel.
     *
     * Every colour returned here must be registered on the panel's ->colors(),
     * or Filament emits the fi-color-* class with no custom properties behind
     * it and the badge renders visibly flat. See .ai/rules/filament.md.
     */
    public function color(): string
    {
        return match ($this) {
            self::Created => 'success',
            self::Updated => 'info',
            self::Deleted, self::LoginFailed => 'danger',
            self::Login, self::Logout => 'zinc',
            self::PasswordReset, self::TwoFactorEnabled, self::TwoFactorDisabled => 'warning',
            self::SettingsUpdated => 'amber',
        };
    }

    /**
     * Whether this event describes a change made to a model record.
     *
     * The panel reads this to decide whether an entry has a before/after diff
     * worth rendering; the authentication events carry context instead.
     */
    public function isModelEvent(): bool
    {
        return in_array($this, [self::Created, self::Updated, self::Deleted], true);
    }
}
