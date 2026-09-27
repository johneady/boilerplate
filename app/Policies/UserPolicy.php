<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;

/**
 * Authorization for managing user accounts.
 *
 * Beyond the permission checks BasePolicy applies, this adds the two
 * self-protection rules the admin panel already enforced in its UI: an
 * administrator may not delete their own account, and may not remove their own
 * administrator rights. Both previously lived only in
 * App\Filament\Resources\Users\UserResource -- as a hidden action and a
 * disabled toggle -- which protects the panel's own forms but nothing else.
 * Stating them here means any future route, console command or API endpoint
 * that authorizes through the Gate inherits them.
 *
 * These two rules deny the ADMINISTRATOR, so they must not be reachable
 * through the Gate::before bypass. AuthServiceProvider therefore skips the
 * bypass for them; see the note there.
 */
class UserPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * No restore or forceDelete: users are not soft-deleted, so those
     * abilities deny everyone through BasePolicy's default.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewUsers,
            'view' => Permission::ViewUsers,
            'create' => Permission::CreateUsers,
            'update' => Permission::UpdateUsers,
            'delete' => Permission::DeleteUsers,
            // Removing someone's access is the same decision as removing the
            // account, so it takes the same permission.
            'deactivate' => Permission::DeleteUsers,
            'reactivate' => Permission::DeleteUsers,
        ];
    }

    /**
     * Determine whether the user may delete this account.
     *
     * Deleting your own account from the admin panel would sign you out mid
     * request and, on a single-administrator instance, leave nobody able to
     * reach the panel at all.
     *
     * An account named on payments or refunds is never deleted -- those
     * columns are write-once -- so it is deactivated instead. AuthServiceProvider
     * sends every delete of a user here, for administrators too, so this holds
     * for them as well.
     *
     * The model is nullable because the Gate may call this with no model at
     * all (a class-form check); there is no self to protect in that case, so
     * only the permission decides.
     */
    public function delete(User $user, mixed $model = null): bool
    {
        if ($model instanceof User && ($user->is($model) || $model->hasFinancialRecords())) {
            return false;
        }

        return parent::delete($user, $model);
    }

    /**
     * Determine whether the user may deactivate this account.
     *
     * Not your own, for delete()'s reason. Not one that is already
     * deactivated, and not one with a running subscription: the customer would
     * go on being billed with no way to sign in and cancel it.
     */
    public function deactivate(User $user, mixed $model = null): bool
    {
        if ($model instanceof User && ($user->is($model) || $model->isDeactivated() || $model->hasRunningSubscription())) {
            return false;
        }

        return $this->allows($user, 'deactivate');
    }

    /**
     * Determine whether the user may reactivate this account.
     */
    public function reactivate(User $user, mixed $model = null): bool
    {
        if ($model instanceof User && ! $model->isDeactivated()) {
            return false;
        }

        return $this->allows($user, 'reactivate');
    }

    /**
     * Determine whether the user may change this account's role.
     *
     * Separate from `update`: editing your own name and email is fine, while
     * demoting yourself is the move that locks an instance out of its own
     * admin panel. The panel disables the role field for the current user, and
     * this is the same rule where the Gate can see it.
     *
     * Nullable for the same class-form reason as delete() above; a check with
     * no target account is a permission question alone.
     */
    public function updateRole(User $user, ?User $model = null): bool
    {
        if ($model !== null && $user->is($model)) {
            return false;
        }

        return $user->hasPermission(Permission::UpdateUsers);
    }
}
