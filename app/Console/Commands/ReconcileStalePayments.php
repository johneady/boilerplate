<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWebhookEvent;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Actions\RefundPayment;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\WebhookEventStatus;
use App\Payments\PaymentManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Finish work whose outcome was never recorded.
 *
 * The recovery path for the gap every gateway operation has: the gateway was
 * asked, and then the process died, timed out or lost the response before the
 * answer was written down. Each operation carries a deterministic idempotency
 * key, so asking again is safe -- the gateway returns what it already did.
 *
 *   - A refund still pending with no gateway id is submitted again with its key.
 *   - A pending refund the gateway has seen, and a checkout still pending,
 *     are re-read from the gateway (a PayPal approval is captured on the way).
 *   - A subscription checkout still unfinished is re-read, which records a
 *     subscription whose webhooks were lost.
 *   - A webhook event stored but never processed (its dispatch failed) is
 *     dispatched again.
 *
 * Each kind has its own batch, and nothing is re-read more often than the
 * staleness window, so refunds a bank is slow to settle can never crowd out
 * payments waiting to be recorded.
 *
 * Also what catches a payment whose webhooks never arrived: a missing or
 * wrong webhook secret delays payment updates by at most one run, rather than
 * losing them.
 *
 * Registered on the schedule in routes/console.php.
 */
class ReconcileStalePayments extends Command
{
    protected $signature = 'payments:reconcile-stale';

    protected $description = 'Re-read payments and refunds whose last gateway operation was never recorded';

    public function handle(PaymentManager $payments, ReconcilePayment $reconcile, RefundPayment $refunds, ReconcileSubscription $reconcileSubscription): int
    {
        $staleBefore = now()->subMinutes((int) config('payments.stale_after_minutes'));
        $limit = (int) config('payments.reconcile_batch_size');
        $touched = 0;

        $pendingRefunds = Refund::query()
            ->where('status', RefundStatus::Pending->value)
            ->where('created_at', '<', $staleBefore)
            // A refund the gateway has acknowledged is re-read through its
            // payment, at most once per window.
            ->where(fn ($query) => $query->whereNull('gateway_refund_id')->orWhereHas(
                'payment',
                fn ($payment) => $payment->whereNull('last_reconciled_at')->orWhere('last_reconciled_at', '<', $staleBefore),
            ))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($pendingRefunds as $refund) {
            try {
                $refund->gateway_refund_id === null
                    ? $refunds->submit($refund)
                    : $reconcile->handle($refund->payment()->firstOrFail(), TransactionSource::Scheduler);
                $touched++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $pendingPayments = Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->where('gateway', '!=', Gateway::Manual->value)
            // A subscription invoice's payment has no checkout, only the
            // gateway's payment id, and a failed first read leaves it pending.
            ->where(fn ($query) => $query->whereNotNull('gateway_checkout_id')->orWhereNotNull('gateway_payment_id'))
            ->where('created_at', '<', $staleBefore)
            // Not re-read more often than the staleness window: an abandoned
            // checkout would otherwise cost an API call every run until it
            // expires.
            ->where(fn ($query) => $query->whereNull('last_reconciled_at')->orWhere('last_reconciled_at', '<', $staleBefore))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($pendingPayments as $payment) {
            try {
                $payments->driverFor($payment)->completeCheckout($payment);
                $reconcile->handle($payment, TransactionSource::Scheduler);
                $touched++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $incompleteSubscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::Incomplete->value)
            ->whereNotNull('gateway_checkout_id')
            ->where('created_at', '<', $staleBefore)
            ->where(fn ($query) => $query->whereNull('last_reconciled_at')->orWhere('last_reconciled_at', '<', $staleBefore))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($incompleteSubscriptions as $subscription) {
            try {
                $reconcileSubscription->handle($subscription, TransactionSource::Scheduler);
                $touched++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        WebhookEvent::query()
            ->where('status', WebhookEventStatus::Received->value)
            ->where('created_at', '<', $staleBefore)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $id) use (&$touched): void {
                ProcessWebhookEvent::dispatch($id);
                $touched++;
            });

        $this->components->info("Reconciled {$touched} stale ".str('record')->plural($touched).'.');

        return self::SUCCESS;
    }
}
