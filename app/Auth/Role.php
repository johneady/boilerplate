<?php

namespace App\Auth;

/**
 * The role assigned to every user, and the permissions it carries.
 *
 * This is deliberately a static, code-defined role table rather than database
 * rows: a boilerplate has to come up usable with no seeding, roles change with
 * deploys rather than at runtime, and an enum is the only version of this that
 * PHPStan can check exhaustively. Swapping in a package like
 * spatie/laravel-permission later means keeping this contract
 * (hasPermission/permissions) and changing where the grants come from --
 * nothing outside this file checks a role name.
 *
 * Cases are ordered least to most privileged, which is what level() reads.
 * The staff roles between User and Admin are not a chain, though -- compare
 * roles with atLeast(), which reads their grants, not their position.
 */
enum Role: string
{
    case User = 'user';

    /** Website content: pages, the media library and contact messages. */
    case Editor = 'editor';

    /** Read-only money: payments, refunds, subscriptions, disputes and tax rates. */
    case Bookkeeper = 'bookkeeper';

    /** Day-to-day operations: everything an Editor and a Bookkeeper do, plus acting on payments. */
    case Manager = 'manager';

    case Admin = 'admin';

    /**
     * The role a newly created account receives.
     *
     * Registration, the factory default and the users table default all read
     * this, so the least-privileged role is the single answer to "what does a
     * new account get" rather than three literals that can drift apart.
     */
    public const Role DEFAULT = self::User;

    /**
     * The label shown wherever this role is displayed to a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Editor => 'Editor',
            self::Bookkeeper => 'Bookkeeper',
            self::Manager => 'Manager',
            self::Admin => 'Administrator',
        };
    }

    /**
     * Supporting copy describing what this role may do.
     */
    public function description(): string
    {
        return match ($this) {
            self::User => 'Can sign in and manage their own account. No access to the admin panel.',
            self::Editor => 'Manages the website\'s content in the admin panel: pages, uploaded files and contact messages. No access to payments, users or settings.',
            self::Bookkeeper => 'Reads payments, refunds, subscriptions, disputes and tax rates. Cannot refund, capture or change any settings.',
            self::Manager => 'Runs day-to-day operations: content, payments, refunds, holds, payment links, subscriptions and the user list. Cannot change settings, credentials, plans or roles.',
            self::Admin => 'Full access, including the admin panel, every user and all application settings.',
        };
    }

    /**
     * The colour this role is badged with in the admin panel.
     */
    public function color(): string
    {
        return match ($this) {
            self::User => 'zinc',
            self::Editor => 'sky',
            self::Bookkeeper => 'emerald',
            self::Manager => 'violet',
            self::Admin => 'amber',
        };
    }

    /**
     * Every permission this role carries.
     *
     * Admin is granted the full set by enumerating Permission::cases() rather
     * than by listing them: a new permission must not silently be denied to
     * administrators, which is the failure mode of a hand-maintained list
     * (and the reason Gate::before exists as a second belt -- see
     * AuthServiceProvider).
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            // Every Editor and Bookkeeper grant, plus acting on payments.
            // SORT_REGULAR because enum cases are not strings; it compares
            // them by identity, dropping the AccessAdminPanel both carry.
            self::Manager => array_values(array_unique([
                ...self::Editor->permissions(),
                ...self::Bookkeeper->permissions(),
                Permission::ViewUsers,
                Permission::RefundPayments,
                Permission::CapturePayments,
                Permission::RecordManualPayments,
                Permission::ManagePaymentLinks,
                Permission::ManageSubscriptions,
            ], SORT_REGULAR)),
            self::Bookkeeper => [
                Permission::AccessAdminPanel,
                Permission::ViewPayments,
            ],
            self::Editor => [
                Permission::AccessAdminPanel,
                Permission::ViewPages,
                Permission::CreatePages,
                Permission::UpdatePages,
                Permission::DeletePages,
                Permission::ViewMedia,
                Permission::DeleteMedia,
                Permission::ViewContactSubmissions,
                Permission::UpdateContactSubmissions,
                Permission::DeleteContactSubmissions,
            ],
            self::User => [],
        };
    }

    /**
     * Whether this role carries the given permission.
     */
    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * How privileged this role is, as its position in the case list.
     *
     * An ordering for display and sorting only -- deciding whether one role
     * covers another is atLeast()'s job, because the staff roles are parallel
     * rather than nested. Ordinal rather than a literal number per case so a
     * role inserted in the middle cannot be given a level that contradicts
     * its position.
     */
    public function level(): int
    {
        $position = array_search($this, self::cases(), true);

        // A case is always present in its own cases(), so this cannot be
        // false; asserting it rather than `?: 0` keeps a real miss loud
        // instead of silently reporting the least-privileged level.
        assert(is_int($position));

        return $position;
    }

    /**
     * Whether this role can do everything another role can.
     *
     * Answered from the grants rather than from level(): the staff roles are
     * not a chain -- a Bookkeeper holds none of an Editor's grants, though it
     * is declared after it -- so "at least an Editor" by position would let a
     * bookkeeper through a check meant for content editors.
     */
    public function atLeast(self $role): bool
    {
        foreach ($role->permissions() as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }
}
