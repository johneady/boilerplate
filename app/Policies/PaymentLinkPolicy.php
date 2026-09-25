<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\PaymentLink;
use App\Models\User;

/**
 * Authorization for payment links.
 *
 * A link that has taken a payment cannot be deleted -- its payments point at
 * it as their payable, and a receipt must keep saying what it was for. Switch
 * it off instead. The administrator bypass stands down for deletion of a
 * payment link (AuthServiceProvider::isSelfProtected()), so this holds for
 * administrators too.
 */
class PaymentLinkPolicy extends BasePolicy
{
    /**
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ManagePaymentLinks,
            'view' => Permission::ManagePaymentLinks,
            'create' => Permission::ManagePaymentLinks,
            'update' => Permission::ManagePaymentLinks,
            'delete' => Permission::ManagePaymentLinks,
        ];
    }

    public function delete(User $user, mixed $model = null): bool
    {
        if ($model instanceof PaymentLink && $model->payments()->exists()) {
            return false;
        }

        return parent::delete($user, $model);
    }

    public function recordManualPayment(User $user, mixed $model = null): bool
    {
        return $user->hasPermission(Permission::RecordManualPayments);
    }
}
