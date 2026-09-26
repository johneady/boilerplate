<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Notifications\Payments\AuthorizationExpiring;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayStatus;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\OpsAlerts;
use Illuminate\Console\Command;
use Throwable;

/**
 * Warn about held payments about to lapse, and record those that have.
 *
 * A hold nobody captures is released by the gateway -- after about seven days
 * at Stripe, three at PayPal -- and the money can then no longer be taken.
 * Operators are warned once per payment (expiry_alerted_at is claimed with a
 * conditional update, so overlapping runs cannot warn twice), and a hold past
 * its expiry is re-read from the gateway and marked expired.
 *
 * Registered on the schedule in routes/console.php.
 */
class CheckPaymentAuthorizations extends Command
{
    protected $signature = 'payments:check-authorizations';

    protected $description = 'Warn about payment holds nearing expiry and expire lapsed ones';

    public function handle(OpsAlerts $opsAlerts, ReconcilePayment $reconcile): int
    {
        $warned = 0;

        Payment::query()
            ->holdsExpiringSoon()
            ->whereNull('expiry_alerted_at')
            ->chunkById(100, function ($payments) use ($opsAlerts, &$warned): void {
                foreach ($payments as $payment) {
                    $claimed = Payment::query()
                        ->whereKey($payment->id)
                        ->whereNull('expiry_alerted_at')
                        ->update(['expiry_alerted_at' => now()]);

                    if ($claimed === 1) {
                        $opsAlerts->send(new AuthorizationExpiring($payment));
                        $warned++;
                    }
                }
            });

        $lapsed = 0;

        Payment::query()
            ->where('status', PaymentStatus::Authorized->value)
            ->whereNotNull('authorization_expires_at')
            ->where('authorization_expires_at', '<=', now())
            ->chunkById(100, function ($payments) use ($reconcile, &$lapsed): void {
                foreach ($payments as $payment) {
                    try {
                        $payment = $reconcile->handle($payment, TransactionSource::Scheduler);

                        // The gateway may not report an expiry of its own yet;
                        // past the deadline the hold cannot be captured anyway.
                        if ($payment->status === PaymentStatus::Authorized) {
                            $payment = $reconcile->apply($payment, new GatewayPaymentState(GatewayStatus::Expired), TransactionSource::Scheduler);
                        }

                        $lapsed += $payment->status === PaymentStatus::Expired ? 1 : 0;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->components->info("Warned about {$warned} and expired {$lapsed} payment ".str('hold')->plural($warned + $lapsed).'.');

        return self::SUCCESS;
    }
}
