<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;

/**
 * Authorization for received webhook events.
 *
 * Readable and retryable by whoever manages payment settings; never created,
 * edited or deleted through the panel (they leave by retention alone).
 */
class WebhookEventPolicy extends BasePolicy
{
    /**
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ManagePaymentSettings,
            'view' => Permission::ManagePaymentSettings,
        ];
    }

    public function retry(User $user, mixed $model = null): bool
    {
        return $user->hasPermission(Permission::ManagePaymentSettings);
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
