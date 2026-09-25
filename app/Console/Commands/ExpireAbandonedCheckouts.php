<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\PaymentManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Mark checkouts nobody finished as expired.
 *
 * The gateway is asked FIRST, every time: a customer who paid in the last
 * minute before the cutoff, or whose PayPal approval was never captured
 * because they closed the tab, is recorded as paid rather than expired. Only
 * a payment the gateway still reports as unfinished is expired -- and the
 * gateway's own checkout is closed first where it allows that (Stripe), so
 * it cannot be paid through afterwards.
 *
 * Registered on the schedule in routes/console.php.
 */
class ExpireAbandonedCheckouts extends Command
{
    protected $signature = 'payments:expire-checkouts';

    protected $description = 'Expire checkouts left unpaid past config(\'payments.abandoned_after_hours\')';

    public function handle(PaymentManager $payments, ReconcilePayment $reconcile): int
    {
        $expired = 0;

        Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->where('gateway', '!=', Gateway::Manual->value)
            ->where('created_at', '<', now()->subHours((int) config('payments.abandoned_after_hours')))
            ->chunkById(100, function ($pending) use ($payments, $reconcile, &$expired): void {
                foreach ($pending as $payment) {
                    try {
                        if ($this->expire($payment, $payments, $reconcile)) {
                            $expired++;
                        }
                    } catch (Throwable $e) {
                        // One unreachable gateway must not stop the rest; the
                        // next run tries this payment again.
                        report($e);
                    }
                }
            });

        $this->components->info("Expired {$expired} abandoned ".str('checkout')->plural($expired).'.');

        return self::SUCCESS;
    }

    private function expire(Payment $payment, PaymentManager $payments, ReconcilePayment $reconcile): bool
    {
        // A subscription invoice's payment has no checkout, but the gateway
        // has already charged it: it is re-read like a checkout, never
        // expired unseen.
        if ($payment->gateway_checkout_id !== null || $payment->gateway_payment_id !== null) {
            $driver = $payments->driverFor($payment);

            $driver->completeCheckout($payment);
            $state = $driver->fetch($payment);
            $payment = $reconcile->apply($payment, $state, TransactionSource::Scheduler);

            // Paid for but still settling (a bank debit, a PayPal capture
            // under review) is not abandoned, however long it takes.
            if ($payment->status !== PaymentStatus::Pending || $state->status === GatewayStatus::Processing) {
                return false;
            }

            $driver->expireCheckout($payment);
            $state = $driver->fetch($payment);
            $payment = $reconcile->apply($payment, $state, TransactionSource::Scheduler);

            if ($state->status === GatewayStatus::Processing) {
                return false;
            }
        }

        // Still open at the gateway (PayPal orders cannot be expired through
        // its API), or never reached it: expire it here. A PayPal approval
        // still outstanding is never captured once the payment is not pending.
        if ($payment->status === PaymentStatus::Pending) {
            $payment = $reconcile->apply($payment, new GatewayPaymentState(GatewayStatus::Expired), TransactionSource::Scheduler);
        }

        return $payment->status === PaymentStatus::Expired;
    }
}
