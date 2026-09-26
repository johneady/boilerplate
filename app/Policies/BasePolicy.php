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
 *
 * The same default covers the abilities Filament asks beyond Laravel's seven
 * -- bulk deletes, reordering, replicating, relation attach/associate. This
 * matters because Filament ALLOWS an ability whose policy method is missing
 * (outside strict mode), so without these every bulk action and reorderable
 * table would be open to any staff role that can merely view the resource.
 * Each "Any" variant and its siblings fall back to the permission of the
 * single-record ability they batch (see FALLBACK_ABILITIES), so a policy
 * mapping `delete` does not have to repeat itself for `deleteAny`.
 */
abstract class BasePolicy
{
    /**
     * The ability whose permission an unmapped ability borrows.
     *
     * Deleting in bulk is still deleting and reordering is updating; an
     * ability absent here and from permissions() is denied.
     *
     * @var array<string, string>
     */
    private const array FALLBACK_ABILITIES = [
        'deleteAny' => 'delete',
        'restoreAny' => 'restore',
        'forceDeleteAny' => 'forceDelete',
        'reorder' => 'update',
        'replicate' => 'create',
    ];

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
     *
     * The model parameter is nullable because the Gate calls policy methods
     * with NO model when an ability is checked against the class
     * (can('view', Page::class)) -- requiring the argument would turn that
     * check into an ArgumentCountError instead of a decision.
     */
    public function view(User $user, mixed $model = null): bool
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
    public function update(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'update');
    }

    /**
     * Determine whether the user may delete this resource.
     */
    public function delete(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'delete');
    }

    /**
     * Determine whether the user may restore this soft-deleted resource.
     */
    public function restore(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'restore');
    }

    /**
     * Determine whether the user may irreversibly delete this resource.
     */
    public function forceDelete(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'forceDelete');
    }

    /**
     * Determine whether the user may delete these resources in bulk.
     */
    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'deleteAny');
    }

    /**
     * Determine whether the user may restore these resources in bulk.
     */
    public function restoreAny(User $user): bool
    {
        return $this->allows($user, 'restoreAny');
    }

    /**
     * Determine whether the user may irreversibly delete these resources in bulk.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->allows($user, 'forceDeleteAny');
    }

    /**
     * Determine whether the user may reorder these resources.
     */
    public function reorder(User $user): bool
    {
        return $this->allows($user, 'reorder');
    }

    /**
     * Determine whether the user may replicate this resource.
     */
    public function replicate(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'replicate');
    }

    /**
     * Relation-manager abilities: denied unless a policy maps them.
     */
    public function attach(User $user): bool
    {
        return $this->allows($user, 'attach');
    }

    public function detach(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'detach');
    }

    public function detachAny(User $user): bool
    {
        return $this->allows($user, 'detachAny');
    }

    public function associate(User $user): bool
    {
        return $this->allows($user, 'associate');
    }

    public function dissociate(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'dissociate');
    }

    public function dissociateAny(User $user): bool
    {
        return $this->allows($user, 'dissociateAny');
    }

    /**
     * Whether the user holds the permission guarding the given ability.
     *
     * An ability with no declared permission -- of its own, or borrowed from
     * the single-record ability it batches -- denies, rather than falling
     * through to allow.
     */
    protected function allows(User $user, string $ability): bool
    {
        $permissions = $this->permissions();

        $permission = $permissions[$ability]
            ?? (isset(self::FALLBACK_ABILITIES[$ability]) ? $permissions[self::FALLBACK_ABILITIES[$ability]] ?? null : null);

        return $permission !== null && $user->hasPermission($permission);
    }
}
