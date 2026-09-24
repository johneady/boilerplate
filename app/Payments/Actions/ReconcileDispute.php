<?php

namespace App\Payments\Actions;

use App\Models\Dispute;
use App\Models\Payment;
use App\Notifications\Payments\DisputeOpened;
use App\Payments\Data\GatewayDispute;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\OpsAlerts;
use App\Payments\PaymentLocator;
use App\Payments\PaymentManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Record a dispute as the gateway currently reports it.
 *
 * Read fresh from the gateway, like payments and subscriptions, so a replayed
 * or out-of-order dispute event cannot move the dispute backwards: the status
 * only changes where DisputeStatus allows it. The dispute is created the
 * first time it is seen, under the disputed payment's row lock so two events
 * racing create one row, and operators are alerted once, behind a claimed
 * timestamp.
 */
class ReconcileDispute
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly PaymentLocator $locator,
        private readonly OpsAlerts $opsAlerts,
    ) {}

    /**
     * Returns null when the disputed payment is not one this application made.
     */
    public function handle(Gateway $gateway, GatewayMode $mode, string $disputeId): ?Dispute
    {
        $reported = $this->payments->disputeDriver($gateway, $mode)->fetchDispute($disputeId);
        $payment = $this->locator->find($gateway, $mode, $reported->payment);

        if ($payment === null) {
            Log::info('Ignored a dispute on a payment this application did not make.', ['gateway' => $gateway->value, 'dispute' => $disputeId]);

            return null;
        }

        [$dispute, $opened] = DB::transaction(function () use ($payment, $reported): array {
            Payment::query()->lockForUpdate()->findOrFail($payment->id);

            // Found across every payment: the gateway's id is unique per
            // gateway, and a later event may name the payment differently.
            $dispute = Dispute::query()->where('gateway', $payment->gateway->value)->where('gateway_dispute_id', $reported->id)->first()
                ?? $payment->disputes()->create([
                    'gateway' => $payment->gateway,
                    'mode' => $payment->mode,
                    'gateway_dispute_id' => $reported->id,
                    'currency' => $reported->amount->currency ?? $payment->currency,
                    'amount' => $reported->amount->amount ?? $payment->amount_captured,
                    'reason' => $reported->reason,
                    'status' => $reported->status,
                    'evidence_due_by' => $reported->evidenceDueBy,
                ]);

            $this->apply($dispute, $reported);

            // Only a dispute still open needs a response; one first seen
            // already decided (an inquiry closed with no chargeback) does not
            // merit an alert asking for evidence.
            $opened = $dispute->status->isOpen()
                && Dispute::query()->whereKey($dispute->id)->whereNull('opened_notified_at')->update(['opened_notified_at' => CarbonImmutable::now()]) === 1;

            return [$dispute, $opened];
        });

        if ($opened) {
            $this->opsAlerts->send(new DisputeOpened($dispute));
        }

        return $dispute;
    }

    private function apply(Dispute $dispute, GatewayDispute $reported): void
    {
        if ($dispute->status->canTransitionTo($reported->status)) {
            $dispute->status = $reported->status;
        } else {
            Log::warning('Ignored a backwards dispute status change.', [
                'dispute' => $dispute->gateway_dispute_id,
                'from' => $dispute->status->value,
                'to' => $reported->status->value,
            ]);
        }

        if ($dispute->status->isOpen()) {
            $dispute->evidence_due_by = $reported->evidenceDueBy ?? $dispute->evidence_due_by;
        } else {
            $dispute->closed_at ??= CarbonImmutable::now();
            $dispute->evidence_due_by = null;
        }

        if ($reported->amount !== null && $reported->amount->currency === $dispute->currency) {
            $dispute->amount = $reported->amount->amount;
        }

        $dispute->reason = $reported->reason ?? $dispute->reason;
        $dispute->save();
    }
}
