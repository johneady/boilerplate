<?php

namespace App\Payments\Drivers\PayPal;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\WebhookEvent;
use App\Payments\CatalogueKey;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayInvoice;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\GatewayStatus;
use App\Payments\Data\GatewaySubscriptionState;
use App\Payments\Data\GatewayTransaction;
use App\Payments\Data\SubscriptionReference;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Money;
use App\Payments\Tax\TaxLine;
use App\Settings\Settings;
use Carbon\CarbonImmutable;

/**
 * PayPal Subscriptions: catalog products, billing plans, and the
 * subscription lifecycle.
 *
 * A PayPal billing plan carries the trial and the tax percentage, and neither
 * can be changed on a plan in use, so a change to either creates a new
 * billing plan and deactivates the old one; existing subscribers stay on the
 * plan they approved. PayPal takes one tax percentage, so the configured
 * rates are combined into one line ("State Tax + City Tax", 8.875%).
 *
 * PayPal has no cancel-at-period-end. Scheduling one suspends the
 * subscription (so nothing more is billed) and payments:end-subscriptions
 * cancels it when the paid period runs out; resuming re-activates it.
 *
 * Subscription payments are PayPal "sales", read and refunded through the v1
 * payments API; one-time payments use Orders v2.
 *
 * Part of PayPalDriver, which supplies $client, $mode and linkHref().
 */
trait ManagesPayPalSubscriptions
{
    public function syncPlan(Plan $plan): void
    {
        $productId = $plan->gatewayRef(Gateway::PayPal, $this->mode);

        if ($productId === null) {
            $product = $this->client->post('/v1/catalogs/products', array_filter([
                'name' => mb_substr($plan->name, 0, 127),
                'type' => 'SERVICE',
                'description' => filled($plan->description) ? mb_substr((string) $plan->description, 0, 256) : null,
            ], fn (mixed $value): bool => $value !== null), CatalogueKey::for('plan', $plan, $this->mode, 'product'));

            $productId = (string) ($product['id'] ?? throw new GatewayException('PayPal did not return the product.'));
            $plan->recordGatewayRef(Gateway::PayPal, $this->mode, $productId);
        }

        foreach ($plan->prices()->get() as $price) {
            $this->syncBillingPlan($plan, $price, $productId);
        }
    }

    public function isSynced(Plan $plan): bool
    {
        if ($plan->gatewayRef(Gateway::PayPal, $this->mode) === null) {
            return false;
        }

        // The relation property, not prices()->get(), and the tax looked up
        // once rather than per price: a table or diagnostics page that
        // eager-loads prices for many plans is then answered from memory
        // instead of queries per plan per price.
        $tax = $this->taxPercentage($plan);

        foreach ($plan->prices as $price) {
            $ref = $price->gatewayRef(Gateway::PayPal, $this->mode);

            if (($ref !== null || $price->is_active) && ($ref['signature'] ?? null) !== $this->billingPlanSignature($plan, $price, $tax)) {
                return false;
            }

            $noTrial = $this->noTrialRef($price);

            if (($noTrial !== null || ($price->is_active && $plan->trial_days > 0))
                && ($noTrial['signature'] ?? null) !== $this->billingPlanSignature($plan, $price, $tax, withTrial: false)) {
                return false;
            }
        }

        return true;
    }

    public function createSubscriptionCheckout(Subscription $subscription, CheckoutUrls $urls): CheckoutSession
    {
        $price = $subscription->price ?? throw new GatewayException('The subscription has no price.');
        $user = $subscription->user ?? throw new GatewayException('The subscription has no user.');
        $planId = $this->billingPlanFor($subscription, $price);

        $names = preg_split('/\s+/', trim($user->name), 2) ?: [];

        $response = $this->client->post('/v1/billing/subscriptions', [
            'plan_id' => $planId,
            'custom_id' => $subscription->uuid,
            'subscriber' => [
                'name' => ['given_name' => mb_substr($names[0] ?? $user->name, 0, 140), 'surname' => mb_substr($names[1] ?? $names[0] ?? $user->name, 0, 140)],
                'email_address' => $user->email,
            ],
            'application_context' => $this->applicationContext($urls),
        ], $subscription->gatewayKey('checkout'));

        $approveUrl = $this->linkHref($response, fn (string $rel, string $href): bool => $rel === 'approve');

        if (! isset($response['id']) || $approveUrl === null) {
            throw new GatewayException('PayPal did not return a subscription to approve.');
        }

        return new CheckoutSession((string) $response['id'], $approveUrl);
    }

    /**
     * The billing plan to start a subscription on: the price's own, which
     * carries the plan's trial, or its no-trial variant for a customer who
     * has had a trial before (the subscription's trial_days is then 0).
     */
    private function billingPlanFor(Subscription $subscription, PlanPrice $price): string
    {
        $ref = $price->gatewayRef(Gateway::PayPal, $this->mode) ?? throw new GatewayException('The price has not been synced to PayPal.');

        if ($subscription->trial_days > 0 || ($price->plan->trial_days ?? 0) === 0) {
            return $ref['id'];
        }

        return $this->noTrialRef($price)['id'] ?? throw new GatewayException('The price\'s no-trial billing plan has not been synced to PayPal.');
    }

    /**
     * A subscription awaiting approval cannot be cancelled through the API;
     * its approval link lapses on its own, and ours is marked expired.
     */
    public function expireSubscriptionCheckout(Subscription $subscription): void {}

    public function fetchSubscription(Subscription $subscription): GatewaySubscriptionState
    {
        $id = $subscription->gateway_subscription_id ?? $subscription->gateway_checkout_id;

        if ($id === null) {
            return new GatewaySubscriptionState(null);
        }

        $paypal = $this->client->get("/v1/billing/subscriptions/{$id}");
        $billing = is_array($paypal['billing_info'] ?? null) ? $paypal['billing_info'] : [];
        $trialing = $this->isInTrial($billing);
        $nextBilling = $this->time($billing['next_billing_time'] ?? null);
        $statusChanged = $this->time($paypal['status_update_time'] ?? null);

        $status = match ($paypal['status'] ?? null) {
            'APPROVAL_PENDING', 'APPROVED' => SubscriptionStatus::Incomplete,
            'ACTIVE' => match (true) {
                // PayPal keeps retrying a failed payment on an ACTIVE
                // subscription until payment_failure_threshold suspends it.
                (int) ($billing['failed_payments_count'] ?? 0) > 0 => SubscriptionStatus::PastDue,
                $trialing => SubscriptionStatus::Trialing,
                default => SubscriptionStatus::Active,
            },
            // Suspended by us for a scheduled cancellation, the subscription
            // is still paid up; suspended by PayPal, it stopped paying.
            'SUSPENDED' => $subscription->cancel_at_period_end ? null : SubscriptionStatus::PastDue,
            'CANCELLED', 'EXPIRED' => SubscriptionStatus::Canceled,
            default => null,
        };

        $ended = $status === SubscriptionStatus::Canceled ? ($statusChanged ?? CarbonImmutable::now()) : null;

        return new GatewaySubscriptionState(
            status: $status,
            subscriptionId: (string) $paypal['id'],
            priceId: isset($paypal['plan_id']) ? (string) $paypal['plan_id'] : null,
            currentPeriodStart: $trialing
                ? $this->time($paypal['start_time'] ?? null)
                : $this->time($billing['last_payment']['time'] ?? null),
            currentPeriodEnd: $nextBilling,
            trialEndsAt: $trialing ? $nextBilling : null,
            cancelAtPeriodEnd: null,
            canceledAt: $ended,
            endedAt: $ended,
            invoices: $status === SubscriptionStatus::Incomplete ? [] : $this->transactions($subscription, (string) $paypal['id']),
        );
    }

    public function invoicePayment(Payment $payment, GatewayInvoice $invoice): GatewayPaymentState
    {
        return $this->fetch($payment);
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): void
    {
        $id = $subscription->gateway_subscription_id ?? throw new GatewayException('The subscription has not started at PayPal.');
        $action = $atPeriodEnd ? 'suspend' : 'cancel';

        try {
            $this->client->post("/v1/billing/subscriptions/{$id}/{$action}", [
                'reason' => $atPeriodEnd ? 'Cancelled by the customer; ends when the paid period does.' : 'Cancelled.',
            ]);
        } catch (GatewayException $e) {
            // Suspending a suspended subscription, or cancelling a cancelled
            // one, is the state asked for; anything else is a real refusal.
            if (! str_contains($e->getMessage(), 'SUBSCRIPTION_STATUS_INVALID')) {
                throw $e;
            }
        }
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        $id = $subscription->gateway_subscription_id ?? throw new GatewayException('The subscription has not started at PayPal.');

        try {
            $this->client->post("/v1/billing/subscriptions/{$id}/activate", ['reason' => 'Resumed by the customer.']);
        } catch (GatewayException $e) {
            if (! str_contains($e->getMessage(), 'SUBSCRIPTION_STATUS_INVALID')) {
                throw $e;
            }
        }
    }

    /**
     * PayPal's revise: the customer re-approves at PayPal, and the new plan
     * takes effect from the next billing cycle, with no proration.
     */
    public function swapSubscription(Subscription $subscription, PlanPrice $price, CheckoutUrls $urls): ?string
    {
        $id = $subscription->gateway_subscription_id ?? throw new GatewayException('The subscription has not started at PayPal.');
        // A plan change never starts a trial, so the no-trial variant where
        // there is one.
        $ref = $price->gatewayRef(Gateway::PayPal, $this->mode);
        $planId = $this->noTrialRef($price)['id'] ?? $ref['id'] ?? throw new GatewayException('The price has not been synced to PayPal.');

        $response = $this->client->post("/v1/billing/subscriptions/{$id}/revise", [
            'plan_id' => $planId,
            'application_context' => $this->applicationContext($urls),
            // As Stripe's swap: Subscription::swapIdempotencyKey().
        ], $subscription->swapIdempotencyKey($price));

        return $this->linkHref($response, fn (string $rel, string $href): bool => $rel === 'approve')
            ?? throw new GatewayException('PayPal did not return a link to approve the change.');
    }

    /**
     * PayPal's automatic-payments page, where the subscriber changes the
     * card or bank account behind the subscription.
     */
    public function paymentMethodUrl(Subscription $subscription, string $returnUrl): ?string
    {
        return $this->mode === GatewayMode::Live
            ? 'https://www.paypal.com/myaccount/autopay/'
            : 'https://www.sandbox.paypal.com/myaccount/autopay/';
    }

    public function endsCancelledSubscriptionsLocally(): bool
    {
        return true;
    }

    public function subscriptionReference(WebhookEvent $event): ?SubscriptionReference
    {
        /** @var array<string, mixed> $resource */
        $resource = $event->payload['resource'] ?? [];

        if (str_starts_with($event->type, 'BILLING.SUBSCRIPTION.')) {
            return new SubscriptionReference(
                uuid: isset($resource['custom_id']) ? (string) $resource['custom_id'] : null,
                subscriptionId: isset($resource['id']) ? (string) $resource['id'] : null,
            );
        }

        // A subscription's payments: the sale names its subscription.
        if (in_array($event->type, ['PAYMENT.SALE.COMPLETED', 'PAYMENT.SALE.DENIED', 'PAYMENT.SALE.PENDING'], true)
            && is_string($resource['billing_agreement_id'] ?? null)) {
            return new SubscriptionReference(subscriptionId: $resource['billing_agreement_id']);
        }

        return null;
    }

    /**
     * A refund of a subscription payment, re-read from PayPal.
     */
    public function refundInEvent(WebhookEvent $event, Payment $payment): ?GatewayRefund
    {
        if ($event->type !== 'PAYMENT.SALE.REFUNDED' || $payment->gateway_checkout_id !== null) {
            return null;
        }

        $refundId = $event->payload['resource']['id'] ?? null;

        return is_string($refundId)
            ? $this->mapSaleRefund($this->client->get("/v1/payments/refund/{$refundId}"), $payment)
            : null;
    }

    /**
     * A subscription payment (a PayPal sale), as fetch() reports payments.
     */
    private function fetchSale(Payment $payment): GatewayPaymentState
    {
        $sale = $this->client->get("/v1/payments/sale/{$payment->gateway_payment_id}");
        $state = $sale['state'] ?? null;

        if (! in_array($state, ['completed', 'partially_refunded', 'refunded'], true)) {
            return new GatewayPaymentState(
                match ($state) {
                    'denied' => GatewayStatus::Failed,
                    'pending' => GatewayStatus::Processing,
                    default => GatewayStatus::Open,
                },
                (string) $payment->gateway_payment_id,
            );
        }

        return new GatewayPaymentState(
            status: GatewayStatus::Captured,
            paymentId: (string) $sale['id'],
            charges: [new GatewayTransaction(
                (string) $sale['id'],
                Money::fromDecimal((string) $sale['amount']['total'], $payment->currency),
                CarbonImmutable::parse((string) ($sale['create_time'] ?? 'now')),
            )],
        );
    }

    /**
     * Refund a subscription payment through the v1 sale API.
     */
    private function refundSale(Payment $payment, Refund $refund): GatewayRefund
    {
        $body = [
            'amount' => ['total' => $refund->money()->toDecimal(), 'currency' => $refund->currency->value],
            // Echoed back on the refund, like custom_id on a v2 refund.
            'invoice_number' => $refund->uuid,
        ];

        if (filled($refund->reason)) {
            $body['description'] = mb_substr((string) $refund->reason, 0, 255);
        }

        return $this->mapSaleRefund(
            $this->client->post("/v1/payments/sale/{$payment->gateway_payment_id}/refund", $body, $refund->idempotency_key),
            $payment,
            $refund->money(),
        );
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function mapSaleRefund(array $refund, Payment $payment, ?Money $fallbackAmount = null): GatewayRefund
    {
        if (! isset($refund['id'])) {
            throw new GatewayException('PayPal did not return the refund.');
        }

        return new GatewayRefund(
            id: (string) $refund['id'],
            amount: isset($refund['amount']['total'])
                ? Money::fromDecimal((string) $refund['amount']['total'], $payment->currency)
                : ($fallbackAmount ?? throw new GatewayException('PayPal did not report the refund amount.')),
            status: match ($refund['state'] ?? null) {
                'completed' => RefundStatus::Succeeded,
                'failed', 'cancelled' => RefundStatus::Failed,
                default => RefundStatus::Pending,
            },
            occurredAt: CarbonImmutable::parse((string) ($refund['create_time'] ?? 'now')),
            reference: isset($refund['invoice_number']) ? (string) $refund['invoice_number'] : null,
        );
    }

    /**
     * Keep a price's billing plans in line with it: the one customers
     * subscribe on and, for a plan with a free trial, a second with no trial
     * for customers who have had theirs (User::isEligibleForTrial()). PayPal
     * fixes the trial on the billing plan, so this is the only way to start a
     * subscription to the same price without one.
     */
    private function syncBillingPlan(Plan $plan, PlanPrice $price, string $productId): void
    {
        $this->syncBillingPlanVariant($plan, $price, $productId, withTrial: true);

        if ($plan->trial_days > 0 || $this->noTrialRef($price) !== null) {
            $this->syncBillingPlanVariant($plan, $price, $productId, withTrial: false);
        }
    }

    /**
     * Create the billing plan one variant needs, replacing one whose trial or
     * tax no longer matches, or deactivate it once the price is retired.
     */
    private function syncBillingPlanVariant(Plan $plan, PlanPrice $price, string $productId, bool $withTrial): void
    {
        $ref = $withTrial ? $price->gatewayRef(Gateway::PayPal, $this->mode) : $this->noTrialRef($price);
        $signature = $this->billingPlanSignature($plan, $price, $this->taxPercentage($plan), $withTrial);

        if (($ref['signature'] ?? null) === $signature || ($ref === null && ! $price->is_active)) {
            return;
        }

        if (! $price->is_active) {
            $this->deactivateBillingPlan((string) $ref['id']);
            $this->recordBillingPlan($price, $withTrial, array_filter([
                'id' => $ref['id'],
                'signature' => $signature,
                'tax_name' => $ref['tax_name'] ?? null,
                'tax_percentage' => $ref['tax_percentage'] ?? null,
            ], fn (?string $value): bool => $value !== null));

            return;
        }

        $tax = $plan->taxable ? TaxRate::combinedActive() : null;
        $currency = $price->currency->value;
        $trialDays = $withTrial ? $plan->trial_days : 0;
        $cycles = [];

        if ($trialDays > 0) {
            $cycles[] = [
                'frequency' => ['interval_unit' => 'DAY', 'interval_count' => min(365, $trialDays)],
                'tenure_type' => 'TRIAL',
                'sequence' => 1,
                'total_cycles' => 1,
            ];
        }

        $cycles[] = [
            'frequency' => ['interval_unit' => $price->interval->paypalUnit(), 'interval_count' => $price->interval_count],
            'tenure_type' => 'REGULAR',
            'sequence' => count($cycles) + 1,
            'total_cycles' => 0,
            'pricing_scheme' => ['fixed_price' => ['value' => $price->money()->toDecimal(), 'currency_code' => $currency]],
        ];

        $body = [
            'product_id' => $productId,
            'name' => mb_substr("{$plan->name} ({$price->intervalDescription()})".($withTrial ? '' : ', no trial'), 0, 127),
            'status' => 'ACTIVE',
            'billing_cycles' => $cycles,
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CONTINUE',
                // Three failed attempts suspend the subscription; the grace
                // period decides how long the subscriber keeps access.
                'payment_failure_threshold' => 3,
            ],
        ];

        if ($tax !== null) {
            $body['taxes'] = ['percentage' => $tax['percentage'], 'inclusive' => false];
        }

        // The plan it replaces is part of the key, so returning to an earlier
        // trial or tax creates a new plan rather than replaying the creation
        // of one deactivated since. The no-trial variant's key is its own, or
        // for a plan with no trial the two would share one billing plan.
        $created = $this->client->post('/v1/billing/plans', $body, CatalogueKey::for('plan-price', $price, $this->mode, sha1(($withTrial ? '' : 'no-trial|').$signature.'|'.($ref['id'] ?? 'none'))));
        $createdId = (string) ($created['id'] ?? throw new GatewayException('PayPal did not return the billing plan.'));

        $this->recordBillingPlan($price, $withTrial, array_filter([
            'id' => $createdId,
            'signature' => $signature,
            'tax_name' => $tax['name'] ?? null,
            'tax_percentage' => $tax['percentage'] ?? null,
        ], fn (mixed $value): bool => $value !== null));

        if ($ref !== null && $ref['id'] !== $createdId) {
            $this->deactivateBillingPlan($ref['id']);
        }
    }

    /**
     * @param  array{id: string, signature?: string, tax_name?: string, tax_percentage?: string}  $ref
     */
    private function recordBillingPlan(PlanPrice $price, bool $withTrial, array $ref): void
    {
        $withTrial
            ? $price->recordGatewayRef(Gateway::PayPal, $this->mode, $ref)
            : $price->recordNoTrialGatewayRef(Gateway::PayPal, $this->mode, $ref);
    }

    /**
     * The billing plan a price is billed on without the plan's trial, once synced.
     *
     * @return array{id: string, history?: list<string>, signature?: string, tax_name?: string, tax_percentage?: string}|null
     */
    private function noTrialRef(PlanPrice $price): ?array
    {
        return $price->gatewayRef(Gateway::PayPal, $this->mode)['no_trial'] ?? null;
    }

    private function deactivateBillingPlan(string $planId): void
    {
        try {
            $this->client->post("/v1/billing/plans/{$planId}/deactivate");
        } catch (GatewayException $e) {
            if (! str_contains($e->getMessage(), 'PLAN_STATUS_INVALID')) {
                throw $e;
            }
        }
    }

    /**
     * What a billing plan must match to still be right for a price: its
     * trial (none, for the no-trial variant), its tax, and whether it is on
     * offer at all.
     */
    private function billingPlanSignature(Plan $plan, PlanPrice $price, string $taxPercentage, bool $withTrial = true): string
    {
        if (! $price->is_active) {
            return 'archived';
        }

        $trialDays = $withTrial ? $plan->trial_days : 0;

        return "trial:{$trialDays}|tax:{$taxPercentage}";
    }

    /**
     * The combined tax a plan's billing plans charge, as its signature has it.
     */
    private function taxPercentage(Plan $plan): string
    {
        return $plan->taxable ? (TaxRate::combinedActive()['percentage'] ?? '0') : '0';
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private function isInTrial(array $billing): bool
    {
        foreach ($billing['cycle_executions'] ?? [] as $cycle) {
            if (($cycle['tenure_type'] ?? null) === 'TRIAL' && (int) ($cycle['cycles_remaining'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every payment PayPal has taken for a subscription, oldest first.
     *
     * Asked for in windows of at most 31 days: PayPal's subscription APIs
     * constrain how far apart start_time and end_time may be, so one
     * request spanning the life of an older subscription can be refused
     * outright. Windows share their edges; the ids key the merge, so a
     * transaction seen twice is kept once.
     *
     * @return list<GatewayInvoice>
     */
    private function transactions(Subscription $subscription, string $paypalId): array
    {
        $tax = $subscription->price?->gatewayRef(Gateway::PayPal, $this->mode);
        $currency = $subscription->currency;
        $invoices = [];

        $end = CarbonImmutable::now()->addDay();
        $start = $this->transactionsSince($subscription);

        // PayPal charges one combined tax, so its receipt line carries the
        // registration number of every rate the plan charges -- named in the
        // tax_name recorded when the plan was synced.
        $registrationNumber = isset($tax['tax_name']) ? TaxRate::registrationNumbersFor($tax['tax_name']) : null;

        while ($start < $end) {
            $windowEnd = min($start->addDays(31), $end);

            $response = $this->client->get("/v1/billing/subscriptions/{$paypalId}/transactions", [
                'start_time' => $start->format('Y-m-d\TH:i:s\Z'),
                'end_time' => $windowEnd->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);

            foreach ($response['transactions'] ?? [] as $transaction) {
                // Refunded ones took the money too; the refunds are recorded
                // against the payment separately.
                if (! in_array($transaction['status'] ?? null, ['COMPLETED', 'PARTIALLY_REFUNDED', 'REFUNDED'], true)) {
                    continue;
                }

                $gross = $transaction['amount_with_breakdown']['gross_amount']['value'] ?? null;

                if (! is_string($gross) && ! is_numeric($gross)) {
                    continue;
                }

                $taxAmount = isset($transaction['amount_with_breakdown']['tax_amount']['value'])
                    ? Money::fromDecimal((string) $transaction['amount_with_breakdown']['tax_amount']['value'], $currency)
                    : Money::zero($currency);

                $invoices[(string) $transaction['id']] = new GatewayInvoice(
                    id: (string) $transaction['id'],
                    paymentId: (string) $transaction['id'],
                    total: Money::fromDecimal((string) $gross, $currency),
                    taxLines: $taxAmount->isPositive()
                        ? [new TaxLine($tax['tax_name'] ?? 'Tax', $tax['tax_percentage'] ?? '0', $taxAmount, $registrationNumber)]
                        : [],
                    paidAt: CarbonImmutable::parse((string) ($transaction['time'] ?? 'now')),
                );
            }

            $start = $windowEnd;
        }

        $invoices = array_values($invoices);

        usort($invoices, fn (GatewayInvoice $a, GatewayInvoice $b): int => $a->paidAt <=> $b->paidAt);

        return $invoices;
    }

    /**
     * Where transactions() starts asking.
     *
     * From the last invoice already recorded as paid, less one window for a
     * transaction PayPal reported late, rather than from the subscription's
     * start: ReconcileSubscription skips an invoice it has already recorded,
     * so re-reading the whole history on every webhook and sweep would cost
     * one request per month of the subscription's age, and a few years in,
     * outlast the job's timeout.
     */
    private function transactionsSince(Subscription $subscription): CarbonImmutable
    {
        $lastPaid = $subscription->payments()->whereNotNull('paid_at')->max('paid_at');

        return $lastPaid !== null
            ? CarbonImmutable::parse($lastPaid)->subDays(31)->utc()
            : ($subscription->created_at ?? CarbonImmutable::now())->subDay()->utc();
    }

    /**
     * @return array<string, string>
     */
    private function applicationContext(CheckoutUrls $urls): array
    {
        return [
            'brand_name' => mb_substr(app(Settings::class)->businessName(), 0, 127),
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'SUBSCRIBE_NOW',
            'return_url' => $urls->returnUrl,
            'cancel_url' => $urls->cancelUrl,
        ];
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
