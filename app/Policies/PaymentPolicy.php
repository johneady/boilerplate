<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;

/**
 * Authorization for payments, refunds and the payment ledger.
 *
 * Read-only through the Gate, like AuditLogPolicy: payments are written by the
 * actions in App\Payments\Actions, never through a create or edit form, and
 * are never deleted. The administrator bypass stands down for these models'
 * write abilities (AuthServiceProvider::IMMUTABLE_RECORD_ABILITIES), so the
 * denials below hold for administrators too.
 *
 * The money-moving abilities -- refund, capture, void, recordManual -- each
 * have their own permission, so a future billing role can be given refunds
 * without being given credentials.
 */
class PaymentPolicy extends BasePolicy
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

    public function refund(User $user, mixed $model = null): bool
    {
        return $user->hasPermission(Permission::RefundPayments);
    }

    public function capture(User $user, mixed $model = null): bool
    {
        return $user->hasPermission(Permission::CapturePayments);
    }

    public function void(User $user, mixed $model = null): bool
    {
        return $user->hasPermission(Permission::CapturePayments);
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
