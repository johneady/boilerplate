<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;

/**
 * Shared behaviour for the application's policies.
 *
 * A policy extending this declares which permission guards each of the seven
 * standard abilities and gets the abilities themselves for free, so the common
 * case -- "this resource is guarded by these four permissions" -- is a handful
 * of lines rather than seven near-identical methods.
 *
 * Abilities a resource does not have (restore and forceDelete without soft
 * deletes, say) are left declaring no permission and deny everyone. Denying by
 * default is the point: a new ability added to Laravel, or a permission
 * someone forgot to map, must refuse rather than allow. Administrators still
 * pass through the Gate::before bypass in AuthServiceProvider, so a missing
 * mapping shows up as "ordinary users cannot do X", never as a hole.
 */
abstract class BasePolicy
{
    /**
     * The permission required for each ability, keyed by ability name.
     *
     * @return array<string, Permission>
     */
    abstract protected function permissions(): array;

    /**
     * Determine whether the user may view any of these resources.
     */
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'viewAny');
    }

    /**
     * Determine whether the user may view this resource.
     */
    public function view(User $user, mixed $model): bool
    {
        return $this->allows($user, 'view');
    }

    /**
     * Determine whether the user may create one of these resources.
     */
    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    /**
     * Determine whether the user may update this resource.
     */
    public function update(User $user, mixed $model): bool
    {
        return $this->allows($user, 'update');
    }

    /**
     * Determine whether the user may delete this resource.
     */
    public function delete(User $user, mixed $model): bool
    {
        return $this->allows($user, 'delete');
    }

    /**
     * Determine whether the user may restore this soft-deleted resource.
     */
    public function restore(User $user, mixed $model): bool
    {
        return $this->allows($user, 'restore');
    }

    /**
     * Determine whether the user may irreversibly delete this resource.
     */
    public function forceDelete(User $user, mixed $model): bool
    {
        return $this->allows($user, 'forceDelete');
    }

    /**
     * Whether the user holds the permission guarding the given ability.
     *
     * An ability with no declared permission denies, rather than falling
     * through to allow.
     */
    protected function allows(User $user, string $ability): bool
    {
        $permission = $this->permissions()[$ability] ?? null;

        return $permission !== null && $user->hasPermission($permission);
    }
}
