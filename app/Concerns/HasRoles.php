<?php

namespace App\Concerns;

use App\Auth\Permission;
use App\Auth\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Role and permission checks for a user model.
 *
 * The model needs a `role` string column; App\Auth\Role does the rest. Every
 * authorization question is answered from the role rather than from a boolean
 * per capability, so granting a new capability is an edit to
 * Role::permissions() rather than a migration.
 *
 * @phpstan-require-extends Model
 *
 * @property Role $role
 * @property bool $is_admin
 */
trait HasRoles
{
    /**
     * Whether this user holds the given role exactly.
     *
     * Accepts the enum or its string value so a check can come straight from
     * a request or a config file without the caller resolving it first; an
     * unrecognised string is false rather than an error, because a typo must
     * fail closed rather than throw a 500 in a gate.
     */
    public function hasRole(Role|string $role): bool
    {
        $role = $role instanceof Role ? $role : Role::tryFrom($role);

        return $role !== null && $this->role === $role;
    }

    /**
     * Whether this user holds any of the given roles.
     *
     * @param  iterable<Role|string>  $roles
     */
    public function hasAnyRole(iterable $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this user's role is at least as privileged as the given one.
     */
    public function hasRoleAtLeast(Role $role): bool
    {
        return $this->role->atLeast($role);
    }

    /**
     * Whether this user's role carries the given permission.
     *
     * This is what policies and gates ask. Note it is NOT the same question as
     * `$user->can()`: that runs the Gate, which consults policies and the
     * Gate::before administrator bypass as well.
     */
    public function hasPermission(Permission $permission): bool
    {
        return $this->role->hasPermission($permission);
    }

    /**
     * Administrator status as a boolean attribute.
     *
     * `role` is the source of truth; this exists because is_admin was the
     * original column and is still read in views, the login redirect, the dev
     * login list and the Filament user resource. Keeping it as a derived
     * accessor means those call sites did not have to be rewritten, and a
     * `$user->is_admin = true` assignment still works -- it sets the role
     * instead, so there is no second place for the truth to live and drift.
     *
     * Assigning false demotes to the default role rather than to "not admin",
     * which is only well-defined while there are two roles. Once a third
     * exists, set the role explicitly instead of assigning this.
     *
     * @return Attribute<bool, bool>
     */
    protected function isAdmin(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->role === Role::Admin,
            set: fn (bool $value): array => [
                'role' => $value ? Role::Admin->value : Role::DEFAULT->value,
            ],
        );
    }

    /**
     * Scope a query to users holding any of the given roles.
     *
     * @param  Builder<static>  $query
     * @param  Role|iterable<Role>  $roles
     */
    public function scopeWithRole(Builder $query, Role|iterable $roles): void
    {
        $values = $roles instanceof Role
            ? [$roles->value]
            : array_map(fn (Role $role): string => $role->value, [...$roles]);

        $query->whereIn('role', $values);
    }
}
