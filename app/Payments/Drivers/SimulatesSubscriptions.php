<?php

namespace App\Payments\Drivers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayInvoice;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewaySubscriptionState;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Money;
use App\Payments\Tax\TaxCalculator;
use App\Payments\Tax\TaxLine;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * The Demo gateway's subscriptions: a checkout page, trials, renewals and
 * failed renewals on demand, with no credentials and no money.
 *
 * Like its payments, a demo subscription's "server-side" state lives in the
 * subscription's metadata['demo'], written only here under the row lock and
 * read back by fetchSubscription() exactly as a real gateway's API is read,
 * so the same ReconcileSubscription path handles all three gateways.
 *
 * Renewals happen only when an administrator presses "Simulate renewal"; a
 * demo subscription is never billed on a timer.
 *
 * Part of DemoDriver.
 */
trait SimulatesSubscriptions
{
    /**
     * Nothing to create; the ids recorded are what fetchSubscription()
     * reports, so a demo subscription's price is recognised like a real one.
     */
    public function syncPlan(Plan $plan): void
    {
        if ($plan->gatewayRef(Gateway::Demo, GatewayMode::Sandbox) === null) {
            foreach (GatewayMode::cases() as $mode) {
                $plan->recordGatewayRef(Gateway::Demo, $mode, "demo_prod_{$plan->id}");
            }
        }

        foreach ($plan->prices()->get() as $price) {
            foreach (GatewayMode::cases() as $mode) {
                if ($price->gatewayRef(Gateway::Demo, $mode) === null) {
                    $price->recordGatewayRef(Gateway::Demo, $mode, ['id' => $this->demoPriceId($price)]);
                }
            }
        }
    }

    public function isSynced(Plan $plan): bool
    {
        // The relation property, so an eager-loaded collection is used
        // rather than one query per plan (see the other drivers).
        foreach ($plan->prices as $price) {
            if ($price->gatewayRef(Gateway::Demo, GatewayMode::Sandbox) === null) {
                return false;
            }
        }

        return $plan->gatewayRef(Gateway::Demo, GatewayMode::Sandbox) !== null;
    }

    public function createSubscriptionCheckout(Subscription $subscription, CheckoutUrls $urls): CheckoutSession
    {
        $this->writeSubscription($subscription, fn (array $state): array => $state + [
            'status' => 'open',
            'price_id' => $subscription->plan_price_id,
            'return_url' => $urls->returnUrl,
            'cancel_url' => $urls->cancelUrl,
            'cancel_at_period_end' => false,
            'invoices' => [],
        ]);

        return new CheckoutSession('demo_sub_'.$subscription->uuid, URL::signedRoute('subscriptions.demo.show', $subscription));
    }

    /**
     * What the customer chose on the demo subscription page. Returns where
     * they go next.
     */
    public function simulateSubscriber(Subscription $subscription, string $outcome): string
    {
        $state = $this->writeSubscription($subscription, function (array $state) use ($subscription, $outcome): array {
            if (($state['status'] ?? null) !== 'open' || $outcome !== 'approve') {
                return $state;
            }

            $now = CarbonImmutable::now();
            $trialDays = $subscription->trial_days;

            if ($trialDays > 0) {
                $trialEnds = $now->addDays($trialDays);

                return [...$state, 'status' => 'trialing', 'trial_ends_at' => $trialEnds->toIso8601String(), 'period_start' => $now->toIso8601String(), 'period_end' => $trialEnds->toIso8601String()];
            }

            return $this->withRenewal([...$state, 'status' => 'active'], $subscription);
        });

        return $outcome === 'approve' ? (string) $state['return_url'] : (string) $state['cancel_url'];
    }

    /**
     * Bill the next period now, as the gateway would when it comes round.
     */
    public function simulateRenewal(Subscription $subscription): void
    {
        $this->writeSubscription($subscription, function (array $state) use ($subscription): array {
            if (! in_array($state['status'] ?? null, ['trialing', 'active', 'past_due'], true) || ($state['cancel_at_period_end'] ?? false)) {
                throw new GatewayException('Only a running demo subscription that is not being cancelled can renew.');
            }

            return $this->withRenewal([...$state, 'status' => 'active'], $subscription);
        });
    }

    /**
     * Fail the next renewal, leaving the subscription past due.
     */
    public function simulateFailedRenewal(Subscription $subscription): void
    {
        $this->writeSubscription($subscription, function (array $state): array {
            if (! in_array($state['status'] ?? null, ['trialing', 'active', 'past_due'], true)) {
                throw new GatewayException('Only a running demo subscription can fail a renewal.');
            }

            return [...$state, 'status' => 'past_due'];
        });
    }

    public function expireSubscriptionCheckout(Subscription $subscription): void
    {
        $this->writeSubscription($subscription, fn (array $state): array => ($state['status'] ?? 'open') === 'open'
            ? [...$state, 'status' => 'expired']
            : $state);
    }

    public function fetchSubscription(Subscription $subscription): GatewaySubscriptionState
    {
        $state = $subscription->fresh()?->metadata['demo'] ?? [];
        $currency = $subscription->currency;

        if ($state === []) {
            return new GatewaySubscriptionState(null);
        }

        $canceledAt = isset($state['canceled_at']) ? CarbonImmutable::parse($state['canceled_at']) : null;

        return new GatewaySubscriptionState(
            status: match ($state['status'] ?? 'open') {
                'trialing' => SubscriptionStatus::Trialing,
                'active' => SubscriptionStatus::Active,
                'past_due' => SubscriptionStatus::PastDue,
                'canceled' => SubscriptionStatus::Canceled,
                'expired' => SubscriptionStatus::Expired,
                default => SubscriptionStatus::Incomplete,
            },
            subscriptionId: ($state['status'] ?? 'open') === 'open' ? null : 'demo_sub_'.$subscription->uuid,
            priceId: 'demo_price_'.$state['price_id'],
            currentPeriodStart: isset($state['period_start']) ? CarbonImmutable::parse($state['period_start']) : null,
            currentPeriodEnd: isset($state['period_end']) ? CarbonImmutable::parse($state['period_end']) : null,
            trialEndsAt: isset($state['trial_ends_at']) ? CarbonImmutable::parse($state['trial_ends_at']) : null,
            cancelAtPeriodEnd: (bool) ($state['cancel_at_period_end'] ?? false),
            canceledAt: $canceledAt,
            endedAt: $canceledAt,
            invoices: array_values(array_map(fn (array $invoice): GatewayInvoice => new GatewayInvoice(
                id: $invoice['id'],
                paymentId: 'demo_pi_'.$invoice['id'],
                total: Money::of((int) $invoice['total'], $currency),
                taxLines: array_values(array_map(fn (array $line): TaxLine => new TaxLine(
                    (string) $line['name'],
                    (string) $line['percentage'],
                    Money::of((int) $line['amount'], $currency),
                    $line['registration_number'] ?? null,
                ), $invoice['tax_lines'])),
                paidAt: CarbonImmutable::parse($invoice['at']),
            ), $state['invoices'] ?? [])),
        );
    }

    /**
     * Record the invoice's charge on the payment's own demo state, as the
     * gateway would hold it, then read it back.
     */
    public function invoicePayment(Payment $payment, GatewayInvoice $invoice): GatewayPaymentState
    {
        $this->write($payment, fn (array $state): array => $state + [
            'status' => 'captured',
            'charges' => [['id' => 'demo_ch_'.$invoice->id, 'amount' => $invoice->total->amount, 'at' => $invoice->paidAt->toIso8601String()]],
            'refunds' => [],
        ]);

        return $this->fetch($payment);
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): void
    {
        $this->writeSubscription($subscription, fn (array $state): array => $atPeriodEnd
            ? [...$state, 'cancel_at_period_end' => true]
            : [...$state, 'status' => 'canceled', 'canceled_at' => CarbonImmutable::now()->toIso8601String()]);
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        $this->writeSubscription($subscription, fn (array $state): array => [...$state, 'cancel_at_period_end' => false]);
    }

    public function swapSubscription(Subscription $subscription, PlanPrice $price, CheckoutUrls $urls): ?string
    {
        $this->writeSubscription($subscription, fn (array $state): array => [...$state, 'price_id' => $price->id]);

        return null;
    }

    public function paymentMethodUrl(Subscription $subscription, string $returnUrl): ?string
    {
        return null;
    }

    /**
     * The demo gateway runs no clock of its own, so payments:end-subscriptions
     * ends a demo subscription whose cancellation falls due.
     */
    public function endsCancelledSubscriptionsLocally(): bool
    {
        return true;
    }

    /**
     * Take the next period's payment: an invoice for the price plus the
     * configured tax, as a gateway billing it would charge.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function withRenewal(array $state, Subscription $subscription): array
    {
        $price = PlanPrice::query()->whereKey($state['price_id'])->firstOrFail();
        // A demo renewal happens when it is asked for, so the new period
        // starts now rather than when the old one would have ended.
        $start = CarbonImmutable::now();

        $breakdown = app(TaxCalculator::class)->calculate(
            $price->money(),
            ($subscription->plan->taxable ?? false) ? TaxRate::query()->active()->get()->map->toCalculatorRate() : [],
        );

        $state['invoices'][] = [
            'id' => 'demo_in_'.$subscription->uuid.'_'.(count($state['invoices'] ?? []) + 1),
            'total' => $breakdown->total()->amount,
            'tax_lines' => $breakdown->linesToArray(),
            'at' => CarbonImmutable::now()->toIso8601String(),
        ];

        return [
            ...$state,
            'period_start' => $start->toIso8601String(),
            'period_end' => $price->interval->addTo($start, $price->interval_count)->toIso8601String(),
        ];
    }

    private function demoPriceId(PlanPrice $price): string
    {
        return "demo_price_{$price->id}";
    }

    /**
     * Change the simulated state under the subscription's row lock.
     *
     * @param  Closure(array<string, mixed>): array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function writeSubscription(Subscription $subscription, Closure $change): array
    {
        return DB::transaction(function () use ($subscription, $change): array {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $metadata = $locked->metadata ?? [];
            $metadata['demo'] = $change($metadata['demo'] ?? []);
            $locked->metadata = $metadata;
            $locked->save();

            return $metadata['demo'];
        });
    }
}
