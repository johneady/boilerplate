<?php

namespace App\Payments\Enums;

/**
 * Where a subscription is in its life, and the moves it may make.
 *
 * Unlike a payment, a subscription cycles: a failed renewal makes it past due
 * and a successful retry makes it active again. What it cannot do is come back
 * once it has ended -- Canceled and Expired are final -- so a late "active"
 * event delivered after a cancellation cannot resurrect it. Every change is
 * checked against canTransitionTo() by
 * App\Payments\Actions\ReconcileSubscription.
 *
 * Plain English only, like PaymentStatus: translate at the point of display.
 */
enum SubscriptionStatus: string
{
    /** Created; the customer has not finished the gateway's checkout. */
    case Incomplete = 'incomplete';

    case Trialing = 'trialing';

    case Active = 'active';

    /** A renewal payment failed and the gateway is retrying it. */
    case PastDue = 'past_due';

    /** Ended after having started. Final. */
    case Canceled = 'canceled';

    /** Never started: the checkout was abandoned or its first payment failed. Final. */
    case Expired = 'expired';

    public function canTransitionTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        return in_array($next, match ($this) {
            self::Incomplete => [self::Trialing, self::Active, self::PastDue, self::Canceled, self::Expired],
            self::Trialing => [self::Active, self::PastDue, self::Canceled],
            self::Active => [self::PastDue, self::Canceled],
            self::PastDue => [self::Active, self::Canceled],
            self::Canceled, self::Expired => [],
        }, true);
    }

    /**
     * Whether the subscription still occupies the user's one subscription
     * slot (subscriptions.active_user_id).
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Incomplete, self::Trialing, self::Active, self::PastDue], true);
    }

    /**
     * Whether the customer has started paying (or trialing), whatever has
     * happened since.
     */
    public function hasStarted(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue, self::Canceled], true);
    }

    /**
     * The values of the started statuses, for whereIn() filters.
     *
     * The one statement of "has this customer ever started a subscription",
     * shared by the trial-burn check (User::isEligibleForTrial()) and the
     * current-subscription ordering (Subscription::currentFirst).
     *
     * @return list<string>
     */
    public static function started(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->hasStarted()),
        ));
    }

    /**
     * The values of the statuses a subscription can grant access in, for
     * whereIn() filters.
     *
     * Deliberately its own list rather than a share of started(): this is the
     * access rule (a cancellation counts until the time already paid for runs
     * out), and it must be able to drift from the trial-burn rule without one
     * silently changing the other. Mirrors the non-false arms of
     * Subscription::grantsAccess().
     *
     * @return list<string>
     */
    public static function grantingAccess(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            [self::Trialing, self::Active, self::PastDue, self::Canceled],
        );
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Canceled, self::Expired], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Incomplete => 'Incomplete',
            self::Trialing => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Canceled => 'Canceled',
            self::Expired => 'Expired',
        };
    }

    /**
     * The badge colour in the admin panel. Must be registered on the panel.
     */
    public function color(): string
    {
        return match ($this) {
            self::Incomplete => 'gray',
            self::Trialing => 'info',
            self::Active => 'success',
            self::PastDue => 'danger',
            self::Canceled, self::Expired => 'zinc',
        };
    }
}
