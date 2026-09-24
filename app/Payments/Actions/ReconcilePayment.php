<?php

namespace App\Payments\Actions;

use App\Jobs\RefundDuplicatePayment;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\Subscription;
use App\Notifications\Payments\DuplicatePaymentRefunded;
use App\Notifications\Payments\PaymentReceipt;
use App\Notifications\Payments\RefundIssued;
use App\Notifications\Payments\SubscriptionRenewed;
use App\Payments\Contracts\Payable;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\GatewayStatus;
use App\Payments\Enums\PaymentAcceptance;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\TransactionType;
use App\Payments\OpsAlerts;
use App\Payments\PaymentManager;
use App\Payments\Tax\RefundTax;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use LogicException;

/**
 * Bring a payment into line with what its gateway says.
 *
 * The single place payment state is written. The return URL, every webhook,
 * the Demo gateway, manual payments, refunds, captures and the scheduled
 * reconciliation all end here, so there is one implementation of the rules
 * and nowhere for two paths to disagree.
 *
 * The gateway is read FIRST, outside any transaction (a slow API call must
 * not hold a row lock). Its answer is then applied in one short transaction
 * under the payment's row lock:
 *
 *   1. every charge and confirmed refund is appended to the ledger, keyed on
 *      the gateway's own transaction id, so anything already recorded is a
 *      no-op however many sources report it;
 *   2. the captured/refunded projections are recomputed from the ledger;
 *   3. the status moves forward if -- and only if -- PaymentStatus allows it;
 *   4. the first time the payment is seen paid, paid_at is claimed and the
 *      payable is told, inside the same transaction.
 *
 * Emails and the duplicate-payment refund are dispatched after commit, each
 * behind its own claimed timestamp, so they happen exactly once.
 */
class ReconcilePayment
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly OpsAlerts $opsAlerts,
    ) {}

    /**
     * Re-read the payment from its gateway and apply what it says.
     */
    public function handle(Payment $payment, TransactionSource $source): Payment
    {
        return $this->apply($payment, $this->payments->driverFor($payment)->fetch($payment), $source);
    }

    /**
     * Apply a gateway state already read.
     */
    public function apply(Payment $payment, GatewayPaymentState $state, TransactionSource $source): Payment
    {
        $outcome = DB::transaction(function () use ($payment, $state, $source): array {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            foreach ($state->charges as $charge) {
                $this->appendLedger($locked, TransactionType::Charge, $charge->id, $charge->amount->amount, $charge->occurredAt, $source);
            }

            $confirmedRefunds = [];

            foreach ($state->refunds as $gatewayRefund) {
                if (($refund = $this->syncRefund($locked, $gatewayRefund, $source)) !== null) {
                    $confirmedRefunds[] = $refund->id;
                }
            }

            $this->recomputeProjections($locked);
            $this->advance($locked, $state);
            $this->fillGatewayIds($locked, $state);

            $locked->last_reconciled_at = CarbonImmutable::now();
            $locked->save();

            return [
                'payment' => $locked,
                'acceptance' => $this->claimPaid($locked),
                'confirmed_refunds' => $confirmedRefunds,
            ];
        });

        /** @var Payment $reconciled */
        $reconciled = $outcome['payment'];

        $this->afterCommit($reconciled, $outcome['acceptance'], $outcome['confirmed_refunds']);

        return $reconciled;
    }

    /**
     * Record one refund result reported by the gateway at the moment it was
     * requested, without re-reading the whole payment.
     */
    public function applyRefund(Payment $payment, GatewayRefund $gatewayRefund, TransactionSource $source): Refund
    {
        $refund = DB::transaction(function () use ($payment, $gatewayRefund, $source): array {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $confirmed = $this->syncRefund($locked, $gatewayRefund, $source);

            $this->recomputeProjections($locked);
            $this->advance($locked, new GatewayPaymentState(GatewayStatus::Captured));
            $locked->save();

            return [$confirmed, $this->findRefund($locked, $gatewayRefund)];
        });

        if ($refund[0] !== null) {
            $this->notifyRefund($refund[0]->id);
        }

        return $refund[1] ?? throw new LogicException('A refund result was applied without a refund row.');
    }

    /**
     * Append one ledger row, ignoring one already recorded for this gateway
     * transaction. Returns whether a row was written.
     */
    private function appendLedger(Payment $payment, TransactionType $type, string $transactionId, int $amount, CarbonImmutable $occurredAt, TransactionSource $source): bool
    {
        return PaymentTransaction::query()->insertOrIgnore([
            'payment_id' => $payment->id,
            'type' => $type->value,
            'amount' => $type === TransactionType::Refund ? -abs($amount) : abs($amount),
            'currency' => $payment->currency->value,
            'gateway' => $payment->gateway->value,
            'gateway_transaction_id' => $transactionId,
            'source' => $source->value,
            'occurred_at' => $occurredAt,
            'created_at' => CarbonImmutable::now(),
        ]) === 1;
    }

    /**
     * Match a gateway refund to our row (creating one for a refund made in the
     * gateway's dashboard), move it forward, and ledger it once confirmed.
     *
     * Returns the refund when this call is the one that confirmed it.
     */
    private function syncRefund(Payment $payment, GatewayRefund $gatewayRefund, TransactionSource $source): ?Refund
    {
        $refund = $this->findRefund($payment, $gatewayRefund) ?? $this->recordDashboardRefund($payment, $gatewayRefund);

        if ($refund->gateway_refund_id === null) {
            $refund->gateway_refund_id = $gatewayRefund->id;
        }

        if ($refund->status->canTransitionTo($gatewayRefund->status)) {
            $refund->status = $gatewayRefund->status;
            $refund->failure_reason = $gatewayRefund->failureReason;
        } elseif ($refund->status !== $gatewayRefund->status) {
            Log::warning('Ignored a backwards refund status change.', [
                'refund' => $refund->uuid,
                'from' => $refund->status->value,
                'to' => $gatewayRefund->status->value,
            ]);
        }

        $refund->save();

        if ($refund->status !== RefundStatus::Succeeded) {
            return null;
        }

        $written = $this->appendLedger($payment, TransactionType::Refund, $gatewayRefund->id, $refund->amount, $gatewayRefund->occurredAt, $source);

        return $written ? $refund : null;
    }

    private function findRefund(Payment $payment, GatewayRefund $gatewayRefund): ?Refund
    {
        $byGatewayId = $payment->refunds()->where('gateway_refund_id', $gatewayRefund->id)->first();

        if ($byGatewayId !== null || $gatewayRefund->reference === null) {
            return $byGatewayId;
        }

        return $payment->refunds()->where('uuid', $gatewayRefund->reference)->first();
    }

    /**
     * A refund issued in the gateway's own dashboard, learned about here.
     */
    private function recordDashboardRefund(Payment $payment, GatewayRefund $gatewayRefund): Refund
    {
        $prior = $payment->refunds()->where('status', '!=', RefundStatus::Failed->value);

        return $payment->refunds()->create([
            'idempotency_key' => "gateway:{$payment->gateway->value}:{$gatewayRefund->id}",
            'gateway' => $payment->gateway,
            'gateway_refund_id' => $gatewayRefund->id,
            'currency' => $payment->currency,
            'amount' => $gatewayRefund->amount->amount,
            'tax_amount' => RefundTax::share(
                amount: $payment->amount,
                taxTotal: $payment->tax_total,
                captured: $payment->amount_captured,
                alreadyRefunded: (int) (clone $prior)->sum('amount'),
                alreadyRefundedTax: (int) (clone $prior)->sum('tax_amount'),
                refund: $gatewayRefund->amount->amount,
            ),
            'reason' => null,
            'status' => RefundStatus::Pending,
            'initiated_by' => null,
        ]);
    }

    /**
     * Recompute the captured and refunded totals from the ledger.
     */
    private function recomputeProjections(Payment $payment): void
    {
        $totals = $payment->transactions()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $payment->amount_captured = (int) ($totals[TransactionType::Charge->value] ?? 0);
        $payment->amount_refunded = abs((int) ($totals[TransactionType::Refund->value] ?? 0));
    }

    /**
     * Move the status forward to what the ledger and gateway now say.
     */
    private function advance(Payment $payment, GatewayPaymentState $state): void
    {
        $next = $this->targetStatus($payment, $state);

        if ($next === null || $next === $payment->status) {
            return;
        }

        if (! $payment->status->canTransitionTo($next)) {
            Log::warning('Ignored a backwards payment status change.', [
                'payment' => $payment->uuid,
                'from' => $payment->status->value,
                'to' => $next->value,
            ]);

            return;
        }

        $now = CarbonImmutable::now();

        match ($next) {
            PaymentStatus::Authorized => $payment->authorized_at = $now,
            PaymentStatus::Succeeded => $payment->captured_at = $now,
            PaymentStatus::Failed => $payment->failed_at = $now,
            PaymentStatus::Voided => $payment->voided_at = $now,
            PaymentStatus::Expired => $payment->expired_at = $now,
            default => null,
        };

        if ($next === PaymentStatus::Failed || $next === PaymentStatus::Voided) {
            $payment->failure_reason = $state->failureReason !== null ? mb_substr($state->failureReason, 0, 255) : $payment->failure_reason;
        }

        $payment->status = $next;
    }

    /**
     * Money on the ledger decides the paid statuses; the gateway's own status
     * decides the rest.
     */
    private function targetStatus(Payment $payment, GatewayPaymentState $state): ?PaymentStatus
    {
        if ($payment->amount_captured > 0) {
            return match (true) {
                $payment->amount_refunded >= $payment->amount_captured => PaymentStatus::Refunded,
                $payment->amount_refunded > 0 => PaymentStatus::PartiallyRefunded,
                default => PaymentStatus::Succeeded,
            };
        }

        return match ($state->status) {
            GatewayStatus::Open => PaymentStatus::Pending,
            GatewayStatus::Authorized => PaymentStatus::Authorized,
            GatewayStatus::Failed => PaymentStatus::Failed,
            GatewayStatus::Canceled => PaymentStatus::Voided,
            GatewayStatus::Expired => PaymentStatus::Expired,
            // Captured with nothing on the ledger: the gateway has not
            // reported the charge yet. Leave the status for the next read.
            GatewayStatus::Captured => null,
        };
    }

    /**
     * Gateway ids are set once, the first time they are known.
     */
    private function fillGatewayIds(Payment $payment, GatewayPaymentState $state): void
    {
        if ($payment->gateway_payment_id === null && $state->paymentId !== null) {
            $payment->gateway_payment_id = $state->paymentId;
        }

        if ($payment->gateway_authorization_id === null && $state->authorizationId !== null) {
            $payment->gateway_authorization_id = $state->authorizationId;
        }

        if ($state->authorizationExpiresAt !== null && $payment->status === PaymentStatus::Authorized) {
            $payment->authorization_expires_at = $state->authorizationExpiresAt;
        }
    }

    /**
     * Claim paid_at the first time the payment is seen paid, and tell the payable.
     *
     * The conditional update is the exactly-once guard: only one caller can
     * move paid_at from null, so the payable hears about each payment once
     * however many reconciles race to it. Runs inside the transaction, so
     * the payable's own writes commit or roll back with the payment's.
     */
    private function claimPaid(Payment $payment): ?PaymentAcceptance
    {
        if (! $payment->status->isPaid() || $payment->paid_at !== null) {
            return null;
        }

        $claimed = Payment::query()
            ->whereKey($payment->id)
            ->whereNull('paid_at')
            ->update(['paid_at' => CarbonImmutable::now()]);

        if ($claimed !== 1) {
            return null;
        }

        $payment->refresh();
        $payable = $payment->payable;

        return $payable instanceof Payable ? $payable->acceptPayment($payment) : PaymentAcceptance::Accepted;
    }

    /**
     * @param  list<int>  $confirmedRefunds
     */
    private function afterCommit(Payment $payment, ?PaymentAcceptance $acceptance, array $confirmedRefunds): void
    {
        if ($acceptance === PaymentAcceptance::Duplicate) {
            RefundDuplicatePayment::dispatch($payment->id)->afterCommit();
            $this->opsAlerts->send(new DuplicatePaymentRefunded($payment));
        } elseif ($acceptance === PaymentAcceptance::Accepted) {
            $this->sendReceipt($payment);
        }

        foreach ($confirmedRefunds as $refundId) {
            $this->notifyRefund($refundId);
        }
    }

    /**
     * Send the receipt once, claimed like paid_at.
     *
     * A subscription's payment gets the subscription's own receipt, which
     * also says when it renews next.
     */
    private function sendReceipt(Payment $payment): void
    {
        // A subscription payment whose subscriber has since deleted their
        // account has nowhere to go.
        if ($payment->customer_email === '') {
            return;
        }

        $claimed = Payment::query()
            ->whereKey($payment->id)
            ->whereNull('receipt_sent_at')
            ->update(['receipt_sent_at' => CarbonImmutable::now()]);

        if ($claimed === 1) {
            Notification::route('mail', $payment->customer_email)->notify(
                $payment->payable_type === (new Subscription)->getMorphClass()
                    ? new SubscriptionRenewed($payment)
                    : new PaymentReceipt($payment),
            );
        }
    }

    /**
     * Tell the customer about a confirmed refund, once.
     */
    private function notifyRefund(int $refundId): void
    {
        $claimed = Refund::query()
            ->whereKey($refundId)
            ->whereNull('notified_at')
            ->update(['notified_at' => CarbonImmutable::now()]);

        if ($claimed !== 1) {
            return;
        }

        $refund = Refund::query()->with('payment')->findOrFail($refundId);
        $payment = $refund->payment;

        if ($payment !== null && $payment->customer_email !== '') {
            Notification::route('mail', $payment->customer_email)->notify(new RefundIssued($refund));
        }
    }
}
