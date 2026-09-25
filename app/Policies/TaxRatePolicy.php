<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\TaxRate;
use App\Models\User;

/**
 * Authorization for tax rates, part of the payment settings.
 *
 * Deleting a rate never synced to a gateway is safe: every one-time payment
 * snapshotted the rates it was charged. One that has been synced is refused,
 * for administrators too (AuthServiceProvider stands the bypass down): the
 * gateway's copy stays on every subscription started with it and keeps being
 * charged, and the receipts of past invoices name the rate through this row.
 * Switching the rate off is how it is retired.
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

    public function delete(User $user, mixed $model = null): bool
    {
        if ($model instanceof TaxRate && filled($model->gateway_refs)) {
            return false;
        }

        return parent::delete($user, $model);
    }
}
