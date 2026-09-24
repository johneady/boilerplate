<?php

namespace App\Payments\Actions;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use App\Payments\PaymentManager;
use App\Payments\Tax\RefundTax;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Return some or all of a payment to the customer.
 *
 *   1. Under the payment's row lock, check the amount against what is still
 *      refundable -- captured, less refunded, less refunds already pending --
 *      and write a pending Refund carrying the caller's idempotency key.
 *   2. Ask the gateway, outside any transaction, with that same key.
 *   3. Record the answer through ReconcilePayment::applyRefund().
 *
 * A double-submitted refund finds its own step-1 row by key and is not made
 * twice. If the gateway cannot be reached the refund stays pending and
 * payments:reconcile-stale asks again with the same key; if it refuses, the
 * refund is marked failed and nothing reaches the ledger.
 */
class RefundPayment
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly ReconcilePayment $reconcile,
    ) {}

    /**
     * @throws PaymentNotAllowed when the payment cannot be refunded by that much
     * @throws GatewayException when the gateway refuses
     */
    public function handle(Payment $payment, Money $amount, string $idempotencyKey, ?string $reason = null, ?User $initiator = null): Refund
    {
        $refund = $this->recordPending($payment, $amount, $idempotencyKey, $reason, $initiator);

        if ($refund->status !== RefundStatus::Pending || $refund->gateway_refund_id !== null) {
            return $refund;
        }

        return $this->submit($refund);
    }

    /**
     * Ask the gateway to carry out a pending refund, or ask again.
     *
     * Public so payments:reconcile-stale can retry a refund whose outcome was
     * never recorded. The idempotency key makes a retry of a refund the
     * gateway already made return that refund rather than make another.
     */
    public function submit(Refund $refund): Refund
    {
        $payment = $refund->payment()->firstOrFail();

        try {
            $gatewayRefund = $this->payments->driverFor($payment)->refund($payment, $refund);
        } catch (GatewayUnavailable $e) {
            report($e);

            return $refund->refresh();
        } catch (GatewayException $e) {
            DB::transaction(function () use ($refund, $e): void {
                $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);

                if ($locked->status->canTransitionTo(RefundStatus::Failed)) {
                    $locked->status = RefundStatus::Failed;
                    $locked->failure_reason = mb_substr($e->getMessage(), 0, 255);
                    $locked->save();
                }
            });

            throw $e;
        }

        return $this->reconcile->applyRefund($payment, $gatewayRefund, TransactionSource::Admin);
    }

    private function recordPending(Payment $payment, Money $amount, string $idempotencyKey, ?string $reason, ?User $initiator): Refund
    {
        try {
            return DB::transaction(function () use ($payment, $amount, $idempotencyKey, $reason, $initiator): Refund {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

                $existing = $locked->refunds()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return $existing;
                }

                if (! in_array($locked->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded], true)) {
                    throw new PaymentNotAllowed(__('Only a paid payment can be refunded.'));
                }

                // Open, the customer's bank is already claiming the money back;
                // lost, it has taken it. A refund on top would pay twice.
                if ($locked->disputes()->whereIn('status', [DisputeStatus::NeedsResponse->value, DisputeStatus::UnderReview->value, DisputeStatus::Lost->value])->exists()) {
                    throw new PaymentNotAllowed(__('This payment is disputed. Respond to the dispute in the gateway\'s dashboard instead of refunding it.'));
                }

                if ($amount->currency !== $locked->currency || ! $amount->isPositive()) {
                    throw new PaymentNotAllowed(__('Enter an amount to refund.'));
                }

                $refundable = $locked->refundableMoney();

                if ($amount->greaterThan($refundable)) {
                    throw new PaymentNotAllowed(__('At most :amount can be refunded.', ['amount' => $refundable->format()]));
                }

                $prior = $locked->refunds()->where('status', '!=', RefundStatus::Failed->value);

                return $locked->refunds()->create([
                    'idempotency_key' => $idempotencyKey,
                    'gateway' => $locked->gateway,
                    'currency' => $locked->currency,
                    'amount' => $amount->amount,
                    'tax_amount' => RefundTax::share(
                        amount: $locked->amount,
                        taxTotal: $locked->tax_total,
                        captured: $locked->amount_captured,
                        alreadyRefunded: (int) (clone $prior)->sum('amount'),
                        alreadyRefundedTax: (int) (clone $prior)->sum('tax_amount'),
                        refund: $amount->amount,
                    ),
                    'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null,
                    'status' => RefundStatus::Pending,
                    'initiated_by' => $initiator?->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return Refund::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }
}
