<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;

/**
 * Authorization for subscription plans and their prices.
 *
 * Neither is ever deleted: subscriptions and their payments name the plan and
 * price they were for. A plan or price is retired by switching it off, which
 * archives it at the gateways. The administrator bypass stands down for
 * deletion here (AuthServiceProvider), so that holds for administrators too.
 * A price is never edited either, beyond switching it off -- the model
 * refuses (App\Concerns\GuardsFinancialRecord).
 */
class PlanPolicy extends BasePolicy
{
    /**
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ManagePlans,
            'view' => Permission::ManagePlans,
            'create' => Permission::ManagePlans,
            'update' => Permission::ManagePlans,
            'sync' => Permission::ManagePlans,
        ];
    }

    public function sync(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'sync');
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
