<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for tax rates, part of the payment settings.
 *
 * Deleting a rate is safe: every payment snapshotted the rates it was charged.
 */
class TaxRatePolicy extends BasePolicy
{
    /**
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ManagePaymentSettings,
            'view' => Permission::ManagePaymentSettings,
            'create' => Permission::ManagePaymentSettings,
            'update' => Permission::ManagePaymentSettings,
            'delete' => Permission::ManagePaymentSettings,
        ];
    }
}
