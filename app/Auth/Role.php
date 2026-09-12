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
 */
enum Role: string
{
    case User = 'user';

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
     * Lets one role be compared against another ("at least an admin") without
     * hardcoding the hierarchy at the call site. Ordinal rather than a literal
     * number per case so a role inserted in the middle cannot be given a level
     * that contradicts its position.
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
     * Whether this role is at least as privileged as another.
     */
    public function atLeast(self $role): bool
    {
        return $this->level() >= $role->level();
    }
}
