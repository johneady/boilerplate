<?php

namespace App\Payments\Actions;

use App\Jobs\RefundDuplicatePayment;
use App\Models\Payment;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Notifications\Payments\AbandonedSubscriptionCanceled;
use App\Notifications\Payments\DuplicateSubscriptionDetected;
use App\Notifications\Payments\SubscriptionCanceled;
use App\Notifications\Payments\SubscriptionPaymentFailed;
use App\Notifications\Payments\SubscriptionStarted;
use App\Payments\Data\GatewayInvoice;
use App\Payments\Data\GatewaySubscriptionState;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\OpsAlerts;
use App\Payments\PaymentManager;
use App\Payments\Tax\TaxLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bring a subscription into line with what its gateway says.
 *
 * The single place subscription state is written, for the same reasons as
 * ReconcilePayment: the return URL, every subscription webhook, the admin
 * actions, the Demo gateway and the scheduled tasks all end here. The gateway
 * is read first, outside any transaction; its answer is applied in one short
 * transaction under the subscription's row lock:
 *
 *   1. the gateway's ids are filled in (once), and the price it is billing
 *      is mapped back to a PlanPrice -- which is how a plan change made at
 *      the gateway (a PayPal revise the customer approved) arrives;
 *   2. the period, trial and cancellation fields are copied across;
 *   3. the status moves if -- and only if -- SubscriptionStatus allows it,
 *      with past_due_since opening and closing the grace period;
 *   4. the one-live-subscription slot (active_user_id) is held or released.
 *
 * Then every paid invoice the gateway reports becomes a Payment, keyed on the
 * invoice so a replayed or out-of-order event records it once, and goes
 * through ReconcilePayment like any other payment (ledger, receipt, refunds).
 *
 * Lifecycle emails are sent once each: the started and ended emails behind
 * claimed timestamps, the failed-renewal email on the transition into
 * past_due, which can only happen once per episode under the lock.
 *
 * A subscription expired here that the gateway later reports running -- a
 * PayPal approval page left open and approved after the customer started
 * again, which PayPal gives no way to withdraw -- is billing someone for
 * nothing. It is cancelled at the gateway and its payments are refunded.
 */
class ReconcileSubscription
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly ReconcilePayment $reconcilePayment,
        private readonly OpsAlerts $opsAlerts,
    ) {}

    public function handle(Subscription $subscription, TransactionSource $source): Subscription
    {
        // Read afresh: how a gateway's answer is interpreted can depend on
        // what is stored here (a PayPal suspension is a scheduled
        // cancellation only if the flag is set), and the caller's copy may
        // predate a change just made.
        $subscription = $subscription->fresh() ?? $subscription;

        $state = $this->payments->subscriptionDriver($subscription->gateway, $subscription->mode)->fetchSubscription($subscription);

        return $this->apply($subscription, $state, $source);
    }

    /**
     * Give up on a subscription whose checkout was never finished.
     *
     * The gateway is asked to stop the checkout being completed later, then
     * re-read -- the customer may have finished at the last moment -- and
     * the subscription is expired only if it still has not started, which
     * frees the user's slot for a new attempt.
     */
    public function expire(Subscription $subscription): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Incomplete) {
            return $subscription;
        }

        if ($subscription->gateway_checkout_id !== null) {
            $this->payments->subscriptionDriver($subscription->gateway, $subscription->mode)->expireSubscriptionCheckout($subscription);
            $subscription = $this->handle($subscription, TransactionSource::Scheduler);
        }

        return $subscription->status === SubscriptionStatus::Incomplete
            ? $this->apply($subscription, new GatewaySubscriptionState(SubscriptionStatus::Expired), TransactionSource::Scheduler)
            : $subscription;
    }

    public function apply(Subscription $subscription, GatewaySubscriptionState $state, TransactionSource $source): Subscription
    {
        [$reconciled, $events] = DB::transaction(function () use ($subscription, $state): array {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $previous = $locked->status;

            $this->fillGatewayIds($locked, $state);
            $this->followPrice($locked, $state);
            $this->copyPeriod($locked, $state);
            $this->advance($locked, $state);
            $duplicate = $this->holdSlot($locked);

            $locked->last_reconciled_at = CarbonImmutable::now();
            $locked->save();

            return [$locked, $this->claimEvents($locked, $previous, $state, $duplicate)];
        });

        foreach ($state->invoices as $invoice) {
            $this->recordInvoice($reconciled, $invoice, $source);
        }

        if ($events['orphaned']) {
            $this->undoOrphan($reconciled, $events['alert_orphan']);
        }

        $this->notify($reconciled, $events);

        return $reconciled;
    }

    private function fillGatewayIds(Subscription $subscription, GatewaySubscriptionState $state): void
    {
        if ($subscription->gateway_subscription_id === null && $state->subscriptionId !== null) {
            $subscription->gateway_subscription_id = $state->subscriptionId;
        }

        if ($subscription->gateway_customer_id === null && $state->customerId !== null) {
            $subscription->gateway_customer_id = $state->customerId;
        }
    }

    /**
     * Follow the price the gateway is billing, and settle a pending change
     * once the gateway has made it.
     */
    private function followPrice(Subscription $subscription, GatewaySubscriptionState $state): void
    {
        if ($state->priceId === null) {
            return;
        }

        $price = PlanPrice::findByGatewayRef($subscription->gateway, $subscription->mode, $state->priceId);

        if ($price === null) {
            Log::warning('A subscription is billed on a price this application does not know.', [
                'subscription' => $subscription->uuid,
                'gateway_price' => $state->priceId,
            ]);

            return;
        }

        if ($price->id !== $subscription->plan_price_id) {
            $subscription->plan_price_id = $price->id;
            $subscription->plan_id = $price->plan_id;
        }

        if ($subscription->pending_plan_price_id === $price->id) {
            $subscription->pending_plan_price_id = null;
        }
    }

    private function copyPeriod(Subscription $subscription, GatewaySubscriptionState $state): void
    {
        $subscription->current_period_start = $state->currentPeriodStart ?? $subscription->current_period_start;
        $subscription->current_period_end = $state->currentPeriodEnd ?? $subscription->current_period_end;
        $subscription->trial_ends_at = $state->trialEndsAt ?? $subscription->trial_ends_at;

        if ($state->cancelAtPeriodEnd !== null) {
            $subscription->cancel_at_period_end = $state->cancelAtPeriodEnd;
        }

        if ($state->canceledAt !== null) {
            $subscription->canceled_at ??= $state->canceledAt;
        }
    }

    private function advance(Subscription $subscription, GatewaySubscriptionState $state): void
    {
        $wasFinal = $subscription->status->isFinal();
        $next = $state->status;

        if ($next !== null && $next !== $subscription->status) {
            if ($subscription->status->canTransitionTo($next)) {
                $subscription->status = $next;
            } else {
                Log::warning('Ignored a backwards subscription status change.', [
                    'subscription' => $subscription->uuid,
                    'from' => $subscription->status->value,
                    'to' => $next->value,
                ]);
            }
        }

        // The grace period runs from the first failure of an episode; a
        // successful retry closes it.
        if ($subscription->status === SubscriptionStatus::PastDue) {
            $subscription->past_due_since ??= CarbonImmutable::now();
        } else {
            $subscription->past_due_since = null;
        }

        // When access stops: the moment it ended, or the end of the paid
        // period for a cancellation still to come.
        $subscription->ends_at = match (true) {
            $wasFinal => $subscription->ends_at,
            $subscription->status->isFinal() => $state->endedAt ?? CarbonImmutable::now(),
            $subscription->cancel_at_period_end => $subscription->current_period_end,
            default => null,
        };
    }

    /**
     * Hold the user's one live-subscription slot while live; release it once
     * ended.
     *
     * A second live subscription for the same user can only come from the
     * gateway (a checkout abandoned here but completed there later). It is
     * reconciled like any other so its payments are recorded, but it cannot
     * take the slot, and operators are warned: the customer is being billed
     * twice. Returns whether that is the case.
     */
    private function holdSlot(Subscription $subscription): bool
    {
        if (! $subscription->status->isLive() || $subscription->user_id === null) {
            $subscription->active_user_id = null;

            return false;
        }

        if ($subscription->active_user_id === $subscription->user_id) {
            return false;
        }

        $taken = Subscription::query()
            ->where('active_user_id', $subscription->user_id)
            ->whereKeyNot($subscription->id)
            ->exists();

        if ($taken) {
            Log::critical('A user has two live subscriptions at the gateway; one may be billing twice.', [
                'subscription' => $subscription->uuid,
                'user' => $subscription->user_id,
            ]);

            return true;
        }

        $subscription->active_user_id = $subscription->user_id;

        return false;
    }

    /**
     * Claim the lifecycle emails this reconcile should send.
     *
     * @return array{started: bool, failed: bool, ended: bool, orphaned: bool, alert_orphan: bool, duplicate: bool}
     */
    private function claimEvents(Subscription $subscription, SubscriptionStatus $previous, GatewaySubscriptionState $state, bool $duplicate): array
    {
        $orphaned = $previous === SubscriptionStatus::Expired
            && in_array($state->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue], true);

        if ($orphaned) {
            // An expired subscription never started, so its ended email is
            // never sent; the claim is reused to alert operators once.
            return ['started' => false, 'failed' => false, 'ended' => false, 'orphaned' => true, 'alert_orphan' => $this->claim($subscription, 'ended_notified_at'), 'duplicate' => false];
        }

        $started = in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)
            && $this->claim($subscription, 'started_notified_at');

        $ended = $subscription->status === SubscriptionStatus::Canceled
            && $this->claim($subscription, 'ended_notified_at');

        return [
            'started' => $started,
            'failed' => $previous !== SubscriptionStatus::PastDue && $subscription->status === SubscriptionStatus::PastDue,
            'ended' => $ended,
            'orphaned' => false,
            'alert_orphan' => false,
            'duplicate' => $duplicate && $this->claim($subscription, 'duplicate_alerted_at'),
        ];
    }

    /**
     * Cancel, at the gateway, a subscription that was expired here but went
     * on to be billed, and refund what it took.
     *
     * Safe to repeat: once cancelled the gateway no longer reports it running,
     * and each refund is keyed on its payment.
     */
    private function undoOrphan(Subscription $subscription, bool $alert): void
    {
        Log::critical('A subscription expired here was completed at the gateway; cancelling and refunding it.', [
            'subscription' => $subscription->uuid,
        ]);

        try {
            $this->payments->subscriptionDriver($subscription->gateway, $subscription->mode)->cancelSubscription($subscription, atPeriodEnd: false);
        } catch (GatewayException $e) {
            // The next reconcile (webhook or scheduled) tries again.
            report($e);
        }

        foreach ($subscription->payments()->whereNotNull('paid_at')->pluck('id') as $paymentId) {
            RefundDuplicatePayment::dispatch((int) $paymentId)->afterCommit();
        }

        if ($alert) {
            $this->opsAlerts->send(new AbandonedSubscriptionCanceled($subscription));
        }
    }

    private function claim(Subscription $subscription, string $column): bool
    {
        $claimed = Subscription::query()->whereKey($subscription->id)->whereNull($column)->update([$column => CarbonImmutable::now()]) === 1;

        if ($claimed) {
            $subscription->setAttribute($column, CarbonImmutable::now());
            $subscription->syncOriginalAttribute($column);
        }

        return $claimed;
    }

    /**
     * Record one paid invoice as a payment, once.
     */
    private function recordInvoice(Subscription $subscription, GatewayInvoice $invoice, TransactionSource $source): void
    {
        $key = "invoice:{$subscription->gateway->value}:{$subscription->mode->value}:{$invoice->id}";
        $payment = Payment::query()->where('idempotency_key', $key)->first();

        if ($payment !== null && $payment->status->isPaid()) {
            return;
        }

        $payment ??= $this->createInvoicePayment($subscription, $invoice, $key);

        $state = $this->payments->subscriptionDriver($subscription->gateway, $subscription->mode)->invoicePayment($payment, $invoice);

        $this->reconcilePayment->apply($payment, $state, $source);
    }

    private function createInvoicePayment(Subscription $subscription, GatewayInvoice $invoice, string $key): Payment
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        try {
            return DB::transaction(function () use ($subscription, $invoice, $key, $user, $plan): Payment {
                $payment = $subscription->payments()->make([
                    'idempotency_key' => $key,
                    'user_id' => $user?->id,
                    'customer_name' => $user->name ?? __('Former customer'),
                    'customer_email' => $user->email ?? '',
                    'description' => mb_substr(__(':plan subscription', ['plan' => $plan->name ?? __('Plan')]), 0, 255),
                    'gateway' => $subscription->gateway,
                    'mode' => $subscription->mode,
                    'status' => PaymentStatus::Pending,
                    'capture_method' => CaptureMethod::Automatic,
                    'currency' => $subscription->currency,
                    'subtotal' => $invoice->subtotal()->amount,
                    'tax_total' => $invoice->taxTotal()->amount,
                    'amount' => $invoice->total->amount,
                    'tax_lines' => array_map(fn (TaxLine $line): array => $line->toArray(), $invoice->taxLines),
                    'metadata' => ['invoice_id' => $invoice->id],
                ]);
                // What the payment is refunded through; known up front, unlike
                // a checkout's, because the gateway has already charged it.
                $payment->gateway_payment_id = $invoice->paymentId;
                $payment->save();

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            // The same invoice reported by two events at once: the other
            // reconcile created it.
            return Payment::query()->where('idempotency_key', $key)->firstOrFail();
        }
    }

    /**
     * @param  array{started: bool, failed: bool, ended: bool, orphaned: bool, alert_orphan: bool, duplicate: bool}  $events
     */
    private function notify(Subscription $subscription, array $events): void
    {
        if ($events['duplicate']) {
            $this->opsAlerts->send(new DuplicateSubscriptionDetected($subscription));
        }

        $user = $subscription->user;

        if ($user === null) {
            return;
        }

        if ($events['started']) {
            $user->notify(new SubscriptionStarted($subscription));
        }

        if ($events['failed']) {
            $user->notify(new SubscriptionPaymentFailed($subscription));
            $this->opsAlerts->send(new SubscriptionPaymentFailed($subscription, forOperators: true));
        }

        if ($events['ended']) {
            $user->notify(new SubscriptionCanceled($subscription));
        }
    }
}
