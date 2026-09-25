<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;

/**
 * Authorization for customers' subscriptions in the admin panel.
 *
 * Read-only through the Gate, like payments: a subscription is written by the
 * actions in App\Payments\Actions from what the gateway says, never through a
 * form, and is never deleted. The cancel, resume and Demo simulation actions
 * have their own permission.
 */
class SubscriptionPolicy extends BasePolicy
{
    /**
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewPayments,
            'view' => Permission::ViewPayments,
        ];
    }

    public function manage(User $user, mixed $model = null): bool
    {
        return $user->hasPermission(Permission::ManageSubscriptions);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function forceDelete(User $user, mixed $model = null): bool
    {
        return false;
    }
}
