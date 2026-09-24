<?php

namespace App\Payments\Drivers;

use App\Models\Payment;
use App\Models\Refund;
use App\Payments\Contracts\PaymentDriver;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\GatewayStatus;
use App\Payments\Data\GatewayTransaction;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\RefundStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * A pretend gateway that takes no money and needs no credentials.
 *
 * It behaves like a hosted gateway -- a checkout page, an outcome the customer
 * chooses, holds, captures and refunds -- so a client demo shows real payment
 * flows, and the reconcile/refund pipeline can be exercised end to end in
 * tests without faking HTTP.
 *
 * Its "server-side" state lives in the payment's metadata['demo'], which
 * fetch() reads back exactly as a real driver reads the gateway's API. Only
 * this driver writes there, always under the payment's row lock.
 *
 * Refused in production by App\Payments\PaymentManager.
 */
class DemoDriver implements PaymentDriver
{
    public function createCheckout(Payment $payment, CheckoutUrls $urls): CheckoutSession
    {
        $this->write($payment, fn (array $state): array => $state + [
            'status' => 'open',
            'return_url' => $urls->returnUrl,
            'cancel_url' => $urls->cancelUrl,
            'charges' => [],
            'refunds' => [],
        ]);

        return new CheckoutSession('demo_cs_'.$payment->uuid, route('payments.demo.show', $payment));
    }

    /**
     * What the customer chose on the demo checkout page.
     *
     * Approving places a hold for a manual-capture payment and takes the money
     * otherwise, like a card payment would. Returns where the customer goes
     * next: the payment's return URL, or its cancel URL.
     */
    public function simulateCustomer(Payment $payment, string $outcome): string
    {
        $state = $this->write($payment, function (array $state) use ($payment, $outcome): array {
            if (($state['status'] ?? null) !== 'open') {
                return $state;
            }

            return match ($outcome) {
                'approve' => $payment->capture_method === CaptureMethod::Manual
                    ? [...$state, 'status' => 'authorized', 'authorized_at' => now()->toIso8601String()]
                    : $this->withCharge($state, $payment, $payment->amount),
                'decline' => [...$state, 'status' => 'failed', 'failure_reason' => 'The demo card was declined.'],
                default => $state,
            };
        });

        return $outcome === 'cancel' ? (string) $state['cancel_url'] : (string) $state['return_url'];
    }

    public function completeCheckout(Payment $payment): void {}

    public function fetch(Payment $payment): GatewayPaymentState
    {
        $state = $payment->fresh()?->metadata['demo'] ?? [];
        $currency = $payment->currency;

        return new GatewayPaymentState(
            status: match ($state['status'] ?? 'open') {
                'authorized' => GatewayStatus::Authorized,
                'captured' => GatewayStatus::Captured,
                'failed' => GatewayStatus::Failed,
                'canceled' => GatewayStatus::Canceled,
                'expired' => GatewayStatus::Expired,
                default => GatewayStatus::Open,
            },
            paymentId: isset($state['status']) && $state['status'] !== 'open' ? 'demo_pi_'.$payment->uuid : null,
            authorizationId: isset($state['authorized_at']) ? 'demo_auth_'.$payment->uuid : null,
            authorizationExpiresAt: isset($state['authorized_at']) ? CarbonImmutable::parse($state['authorized_at'])->addDays(7) : null,
            charges: array_values(array_map(fn (array $charge): GatewayTransaction => new GatewayTransaction(
                $charge['id'],
                Money::of($charge['amount'], $currency),
                CarbonImmutable::parse($charge['at']),
            ), $state['charges'] ?? [])),
            refunds: array_values(array_map(fn (array $refund): GatewayRefund => new GatewayRefund(
                $refund['id'],
                Money::of($refund['amount'], $currency),
                RefundStatus::from($refund['status']),
                CarbonImmutable::parse($refund['at']),
                reference: $refund['reference'],
            ), $state['refunds'] ?? [])),
            failureReason: $state['failure_reason'] ?? null,
        );
    }

    public function capture(Payment $payment, Money $amount): void
    {
        $this->write($payment, function (array $state) use ($payment, $amount): array {
            if (($state['status'] ?? null) === 'captured') {
                return $state;
            }

            if (($state['status'] ?? null) !== 'authorized') {
                throw new GatewayException('Only an authorized demo payment can be captured.');
            }

            return $this->withCharge($state, $payment, $amount->amount);
        });
    }

    public function void(Payment $payment): void
    {
        $this->write($payment, function (array $state): array {
            if (($state['status'] ?? null) === 'canceled') {
                return $state;
            }

            if (($state['status'] ?? null) !== 'authorized') {
                throw new GatewayException('Only an authorized demo payment can be voided.');
            }

            return [...$state, 'status' => 'canceled'];
        });
    }

    public function refund(Payment $payment, Refund $refund): GatewayRefund
    {
        $id = 'demo_re_'.$refund->uuid;

        $state = $this->write($payment, function (array $state) use ($refund, $id): array {
            // Keyed like a real gateway's idempotency: asking again returns
            // the refund already made rather than a second one.
            foreach ($state['refunds'] ?? [] as $existing) {
                if ($existing['id'] === $id) {
                    return $state;
                }
            }

            $state['refunds'][] = [
                'id' => $id,
                'amount' => $refund->amount,
                'status' => RefundStatus::Succeeded->value,
                'at' => now()->toIso8601String(),
                'reference' => $refund->uuid,
            ];

            return $state;
        });

        foreach ($state['refunds'] as $made) {
            if ($made['id'] === $id) {
                return new GatewayRefund($id, Money::of((int) $made['amount'], $payment->currency), RefundStatus::Succeeded, CarbonImmutable::parse((string) $made['at']), $refund->uuid);
            }
        }

        throw new GatewayException('The demo refund was not recorded.');
    }

    public function expireCheckout(Payment $payment): void
    {
        $this->write($payment, fn (array $state): array => ($state['status'] ?? 'open') === 'open'
            ? [...$state, 'status' => 'expired']
            : $state);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function withCharge(array $state, Payment $payment, int $amount): array
    {
        return [
            ...$state,
            'status' => 'captured',
            'charges' => [['id' => 'demo_ch_'.$payment->uuid, 'amount' => $amount, 'at' => now()->toIso8601String()]],
        ];
    }

    /**
     * Change the simulated state under the payment's row lock.
     *
     * @param  Closure(array<string, mixed>): array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function write(Payment $payment, Closure $change): array
    {
        return DB::transaction(function () use ($payment, $change): array {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $metadata = $locked->metadata ?? [];
            $metadata['demo'] = $change($metadata['demo'] ?? []);
            $locked->metadata = $metadata;
            $locked->save();

            return $metadata['demo'];
        });
    }
}
