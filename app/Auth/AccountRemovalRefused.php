<?php

namespace App\Auth;

use App\Models\User;
use RuntimeException;

/**
 * Thrown when deleting or deactivating an account would break something that
 * outlives it.
 *
 * Raised from the User model itself rather than only refused by UserPolicy,
 * which covers the panel alone: a console command, a queued job or a tinker
 * session is stopped by the same rule. The policy and the panel's tooltips are
 * how a person learns about it before they get this far.
 *
 * A RuntimeException rather than a LogicException, unlike
 * ImmutableRecordException: an account's state can change between the check a
 * form made and the moment it acts, so reaching this is not always a bug.
 */
class AccountRemovalRefused extends RuntimeException
{
    /**
     * Whether the refusal protects the last active administrator.
     *
     * Told apart because the advice differs: an account kept for its
     * financial records should be deactivated instead, while the last
     * administrator cannot be deactivated either.
     */
    private bool $protectsLastAdministrator = false;

    /**
     * The account is the only active administrator left.
     */
    public static function lastAdministrator(User $user): self
    {
        $exception = new self(sprintf('User #%s is the last active administrator; removing it would lock everyone out of the admin panel.', $user->getKey()));
        $exception->protectsLastAdministrator = true;

        return $exception;
    }

    /**
     * Whether the account was kept because it is the last active administrator.
     */
    public function protectsLastAdministrator(): bool
    {
        return $this->protectsLastAdministrator;
    }

    /**
     * Payments or refunds name the account as the one who recorded or issued them.
     */
    public static function holdsFinancialRecords(User $user): self
    {
        return new self(sprintf('User #%s recorded payments or issued refunds, which must keep naming them; deactivate the account instead.', $user->getKey()));
    }

    /**
     * The account has a subscription still billing at the gateway.
     */
    public static function hasRunningSubscription(User $user): self
    {
        return new self(sprintf('User #%s has a running subscription; cancel it before deactivating the account.', $user->getKey()));
    }
}
