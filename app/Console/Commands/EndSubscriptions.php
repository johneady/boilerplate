<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Payments\Actions\CancelSubscription;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\PaymentManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * End subscriptions whose time is up.
 *
 *   - A cancellation scheduled for the period end, on a gateway that has no
 *     such option of its own (PayPal, which was suspended meanwhile, and the
 *     Demo gateway), is carried out once the period has run out. Stripe ends
 *     its own at the period end and reports it by webhook.
 *   - A subscription checkout nobody finished is expired after
 *     config('payments.abandoned_after_hours'), freeing the user's slot. It
 *     is re-read first, so one completed at the last moment is kept.
 *
 * A past-due subscription whose grace period has run out needs nothing
 * here: access is decided from past_due_since when asked (see
 * Subscription::grantsAccess()), and the gateway cancels it once its own
 * retries are exhausted.
 *
 * Registered on the schedule in routes/console.php.
 */
class EndSubscriptions extends Command
{
    protected $signature = 'payments:end-subscriptions';

    protected $description = 'End subscriptions cancelled at period end, and expire unfinished subscription checkouts';

    public function handle(PaymentManager $payments, CancelSubscription $cancel, ReconcileSubscription $reconcile): int
    {
        $ended = 0;
        $expired = 0;

        Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->where('cancel_at_period_end', true)
            ->where('ends_at', '<=', now())
            ->chunkById(100, function ($due) use ($payments, $cancel, &$ended): void {
                foreach ($due as $subscription) {
                    if (! $payments->subscriptionDriver($subscription->gateway, $subscription->mode)->endsCancelledSubscriptionsLocally()) {
                        continue;
                    }

                    try {
                        $cancel->handle($subscription, atPeriodEnd: false);
                        $ended++;
                    } catch (Throwable $e) {
                        // The next run tries again; one gateway being down
                        // must not stop the rest.
                        report($e);
                    }
                }
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::Incomplete->value)
            ->where('created_at', '<', now()->subHours((int) config('payments.abandoned_after_hours')))
            ->chunkById(100, function ($abandoned) use ($reconcile, &$expired): void {
                foreach ($abandoned as $subscription) {
                    try {
                        if ($reconcile->expire($subscription)->status === SubscriptionStatus::Expired) {
                            $expired++;
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->components->info("Ended {$ended} ".str('subscription')->plural($ended).", expired {$expired} unfinished ".str('checkout')->plural($expired).'.');

        return self::SUCCESS;
    }
}
