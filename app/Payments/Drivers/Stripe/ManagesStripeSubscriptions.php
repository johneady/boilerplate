<?php

namespace App\Payments\Drivers\Stripe;

use App\Models\BillingCustomer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\WebhookEvent;
use App\Payments\CatalogueKey;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayInvoice;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewaySubscriptionState;
use App\Payments\Data\SubscriptionReference;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Money;
use App\Payments\Tax\TaxLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Stripe\StripeObject;

/**
 * Stripe Billing: products and prices, Checkout in subscription mode, and the
 * subscription lifecycle.
 *
 * Tax on subscriptions is charged by Stripe from TaxRate objects synced from
 * the configured rates, because Stripe computes
 * every renewal invoice itself. Stripe TaxRates cannot change percentage, so
 * an edited rate becomes a new Stripe TaxRate and the old one is archived;
 * existing subscriptions keep the rates they started with.
 *
 * Part of StripeDriver, which supplies client(), call() and $mode.
 */
trait ManagesStripeSubscriptions
{
    private const array SUBSCRIPTION_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'customer.subscription.paused',
        'customer.subscription.resumed',
    ];

    private const array INVOICE_EVENTS = [
        'invoice.paid',
        'invoice.payment_failed',
        'invoice.payment_action_required',
    ];

    public function syncPlan(Plan $plan): void
    {
        $productId = $plan->gatewayRef(Gateway::Stripe, $this->mode);
        $product = array_filter([
            'name' => mb_substr($plan->name, 0, 250),
            'description' => filled($plan->description) ? mb_substr((string) $plan->description, 0, 1000) : null,
            'active' => $plan->is_active,
            'metadata' => ['plan_key' => $plan->key],
        ], fn (mixed $value): bool => $value !== null);

        if ($productId === null) {
            $created = $this->call(fn () => $this->client()->products->create($product, ['idempotency_key' => CatalogueKey::for('plan', $plan, $this->mode, 'product')]));
            $plan->recordGatewayRef(Gateway::Stripe, $this->mode, (string) $created->id);
            $productId = (string) $created->id;
        } else {
            // Stripe keeps the name customers see on their invoices, so it
            // follows the plan.
            $this->call(fn () => $this->client()->products->update($productId, $product));
        }

        foreach ($plan->prices()->get() as $price) {
            $this->syncStripePrice($price, $productId);
        }

        if ($plan->taxable) {
            $this->syncTaxRates();
        }
    }

    public function isSynced(Plan $plan): bool
    {
        if ($plan->gatewayRef(Gateway::Stripe, $this->mode) === null) {
            return false;
        }

        // The relation property, not prices()->get(): a table or diagnostics
        // page that eager-loads prices for many plans is then answered from
        // memory instead of one query per plan per gateway.
        foreach ($plan->prices as $price) {
            $ref = $price->gatewayRef(Gateway::Stripe, $this->mode);

            // An inactive price never synced needs nothing; any other price
            // must be at Stripe in the state it is here.
            if (($ref !== null || $price->is_active) && ($ref['signature'] ?? null) !== $this->priceSignature($price)) {
                return false;
            }
        }

        return true;
    }

    public function createSubscriptionCheckout(Subscription $subscription, CheckoutUrls $urls): CheckoutSession
    {
        $price = $subscription->price ?? throw new GatewayException('The subscription has no price.');
        $plan = $subscription->plan ?? throw new GatewayException('The subscription has no plan.');
        $priceRef = $price->gatewayRef(Gateway::Stripe, $this->mode)['id'] ?? throw new GatewayException('The price has not been synced to Stripe.');

        $subscriptionData = ['metadata' => ['subscription_uuid' => $subscription->uuid]];

        if ($subscription->trial_days > 0) {
            $subscriptionData['trial_period_days'] = $subscription->trial_days;
        }

        if ($plan->taxable && ($taxRates = $this->syncTaxRates()) !== []) {
            $subscriptionData['default_tax_rates'] = $taxRates;
        }

        $session = $this->call(fn () => $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $this->billingCustomer($subscription),
            'client_reference_id' => $subscription->uuid,
            'line_items' => [['price' => $priceRef, 'quantity' => 1]],
            'metadata' => ['subscription_uuid' => $subscription->uuid],
            'subscription_data' => $subscriptionData,
            'success_url' => $urls->returnUrl,
            'cancel_url' => $urls->cancelUrl,
            'expires_at' => $this->checkoutExpiresAt(),
        ], ['idempotency_key' => $subscription->gatewayKey('checkout')]));

        return new CheckoutSession((string) $session->id, (string) $session->url);
    }

    public function expireSubscriptionCheckout(Subscription $subscription): void
    {
        if ($subscription->gateway_checkout_id === null) {
            return;
        }

        try {
            $this->call(fn () => $this->client()->checkout->sessions->expire($subscription->gateway_checkout_id));
        } catch (GatewayUnavailable $e) {
            throw $e;
        } catch (GatewayException) {
            // Already completed or already expired; the caller re-reads it.
        }
    }

    public function fetchSubscription(Subscription $subscription): GatewaySubscriptionState
    {
        $subscriptionId = $subscription->gateway_subscription_id;

        if ($subscriptionId === null) {
            if ($subscription->gateway_checkout_id === null) {
                return new GatewaySubscriptionState(null);
            }

            $session = $this->call(fn () => $this->client()->checkout->sessions->retrieve($subscription->gateway_checkout_id))->toArray();
            $subscriptionId = is_string($session['subscription'] ?? null) ? $session['subscription'] : null;

            if ($subscriptionId === null) {
                return new GatewaySubscriptionState(($session['status'] ?? null) === 'expired' ? SubscriptionStatus::Expired : null);
            }
        }

        $stripe = $this->call(fn () => $this->client()->subscriptions->retrieve($subscriptionId))->toArray();
        $item = $stripe['items']['data'][0] ?? [];

        return new GatewaySubscriptionState(
            status: match ($stripe['status'] ?? null) {
                'incomplete' => SubscriptionStatus::Incomplete,
                'incomplete_expired' => SubscriptionStatus::Expired,
                'trialing' => SubscriptionStatus::Trialing,
                'active' => SubscriptionStatus::Active,
                // unpaid: Stripe gave up retrying but kept the subscription;
                // paused: a trial ended with no payment method. Either way
                // the customer owes a payment, and the grace period applies.
                'past_due', 'unpaid', 'paused' => SubscriptionStatus::PastDue,
                'canceled' => SubscriptionStatus::Canceled,
                default => null,
            },
            subscriptionId: (string) $stripe['id'],
            customerId: is_string($stripe['customer'] ?? null) ? $stripe['customer'] : null,
            priceId: isset($item['price']['id']) ? (string) $item['price']['id'] : null,
            currentPeriodStart: $this->timestamp($item['current_period_start'] ?? null),
            currentPeriodEnd: $this->timestamp($item['current_period_end'] ?? null),
            trialEndsAt: $this->timestamp($stripe['trial_end'] ?? null),
            // Newer API versions schedule a period-end cancellation as
            // cancel_at rather than the flag; both mean the same here.
            cancelAtPeriodEnd: (bool) ($stripe['cancel_at_period_end'] ?? false) || isset($stripe['cancel_at']),
            canceledAt: $this->timestamp($stripe['canceled_at'] ?? null),
            endedAt: $this->timestamp($stripe['ended_at'] ?? null),
            invoices: $this->paidInvoices((string) $stripe['id'], $subscription->currency),
        );
    }

    public function invoicePayment(Payment $payment, GatewayInvoice $invoice): GatewayPaymentState
    {
        return $this->fetch($payment);
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): void
    {
        $id = $subscription->gateway_subscription_id ?? throw new GatewayException('The subscription has not started at Stripe.');

        $atPeriodEnd
            ? $this->call(fn () => $this->client()->subscriptions->update($id, ['cancel_at_period_end' => true]))
            : $this->call(fn () => $this->client()->subscriptions->cancel($id));
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        $id = $subscription->gateway_subscription_id ?? throw new GatewayException('The subscription has not started at Stripe.');

        $this->call(fn () => $this->client()->subscriptions->update($id, ['cancel_at_period_end' => false]));
    }

    public function swapSubscription(Subscription $subscription, PlanPrice $price, CheckoutUrls $urls): ?string
    {
        $id = $subscription->gateway_subscription_id ?? throw new GatewayException('The subscription has not started at Stripe.');
        $priceRef = $price->gatewayRef(Gateway::Stripe, $this->mode)['id'] ?? throw new GatewayException('The price has not been synced to Stripe.');

        $stripe = $this->call(fn () => $this->client()->subscriptions->retrieve($id))->toArray();
        $itemId = $stripe['items']['data'][0]['id'] ?? throw new GatewayException('The Stripe subscription has no item to change.');

        $this->call(fn () => $this->client()->subscriptions->update($id, [
            'items' => [['id' => (string) $itemId, 'price' => $priceRef]],
            // The customer is charged or credited for the rest of the
            // current period on the next invoice.
            'proration_behavior' => 'create_prorations',
        ], ['idempotency_key' => $subscription->swapIdempotencyKey($price)]));

        return null;
    }

    /**
     * A Stripe Billing Portal session. The portal must have been saved once
     * in the Stripe dashboard (Settings -> Billing -> Customer portal) for
     * each mode; until then Stripe refuses, and the billing page says so.
     */
    public function paymentMethodUrl(Subscription $subscription, string $returnUrl): ?string
    {
        $customer = $subscription->gateway_customer_id ?? throw new GatewayException('The subscription has no Stripe customer.');

        $session = $this->call(fn () => $this->client()->billingPortal->sessions->create([
            'customer' => $customer,
            'return_url' => $returnUrl,
        ]));

        return (string) $session->url;
    }

    public function endsCancelledSubscriptionsLocally(): bool
    {
        return false;
    }

    public function subscriptionReference(WebhookEvent $event): ?SubscriptionReference
    {
        /** @var array<string, mixed> $object */
        $object = $event->payload['data']['object'] ?? [];

        if (in_array($event->type, self::SUBSCRIPTION_EVENTS, true)) {
            return new SubscriptionReference(
                uuid: $this->stringOrNull($object['metadata']['subscription_uuid'] ?? null),
                subscriptionId: $this->stringOrNull($object['id'] ?? null),
            );
        }

        if (in_array($event->type, self::INVOICE_EVENTS, true)) {
            $details = $object['parent']['subscription_details'] ?? [];
            $reference = new SubscriptionReference(
                uuid: $this->stringOrNull($details['metadata']['subscription_uuid'] ?? null),
                subscriptionId: $this->stringOrNull($details['subscription'] ?? $object['subscription'] ?? null),
            );

            return $reference->isEmpty() ? null : $reference;
        }

        if (in_array($event->type, self::CHECKOUT_EVENTS, true) && ($object['mode'] ?? null) === 'subscription') {
            return new SubscriptionReference(
                uuid: $this->stringOrNull($object['metadata']['subscription_uuid'] ?? null),
                checkoutId: $this->stringOrNull($object['id'] ?? null),
            );
        }

        return null;
    }

    /**
     * Create the Stripe price for a plan price, or archive it.
     */
    private function syncStripePrice(PlanPrice $price, string $productId): void
    {
        $ref = $price->gatewayRef(Gateway::Stripe, $this->mode);
        $signature = $this->priceSignature($price);

        if ($ref === null) {
            // A price retired before it was ever synced has nothing to archive.
            if (! $price->is_active) {
                return;
            }

            $created = $this->call(fn () => $this->client()->prices->create([
                'product' => $productId,
                'currency' => $price->currency->stripeCode(),
                'unit_amount' => $price->amount,
                'recurring' => ['interval' => $price->interval->value, 'interval_count' => $price->interval_count],
                'tax_behavior' => 'exclusive',
                'metadata' => ['plan_price_id' => (string) $price->id],
            ], ['idempotency_key' => CatalogueKey::for('plan-price', $price, $this->mode)]));

            $price->recordGatewayRef(Gateway::Stripe, $this->mode, ['id' => (string) $created->id, 'signature' => $signature]);

            return;
        }

        if (($ref['signature'] ?? null) !== $signature) {
            $this->call(fn () => $this->client()->prices->update($ref['id'], ['active' => $price->is_active]));
            $price->recordGatewayRef(Gateway::Stripe, $this->mode, ['id' => $ref['id'], 'signature' => $signature]);
        }
    }

    /**
     * A Stripe price is immutable apart from whether it is on offer.
     */
    private function priceSignature(PlanPrice $price): string
    {
        return $price->is_active ? 'active' : 'archived';
    }

    /**
     * Make sure every active tax rate has a matching Stripe TaxRate, and
     * return their ids.
     *
     * @return list<string>
     */
    private function syncTaxRates(): array
    {
        $ids = [];

        foreach (TaxRate::query()->active()->get() as $rate) {
            $signature = $rate->name.'|'.$rate->percentage;
            $ref = $rate->gateway_refs[Gateway::Stripe->value][$this->mode->value] ?? null;

            if (is_array($ref) && ($ref['signature'] ?? null) === $signature) {
                $ids[] = (string) $ref['id'];

                continue;
            }

            $created = $this->call(fn () => $this->client()->taxRates->create([
                'display_name' => mb_substr($rate->name, 0, 50),
                // The SDK types this as a float and encodes it straight back to
                // a decimal string; a three-place percentage survives that
                // round trip exactly ("9.975"). Nothing is calculated with it.
                'percentage' => (float) $rate->percentage,
                'inclusive' => false,
                // The rate it replaces is part of the key, so going back to an
                // earlier value creates a new rate rather than replaying the
                // creation of one archived since.
            ], ['idempotency_key' => CatalogueKey::for('tax-rate', $rate, $this->mode, sha1($signature.'|'.($ref['id'] ?? 'none')))]));

            if (is_array($ref) && isset($ref['id'])) {
                $this->call(fn () => $this->client()->taxRates->update((string) $ref['id'], ['active' => false]));
            }

            $refs = $rate->gateway_refs ?? [];
            $refs[Gateway::Stripe->value][$this->mode->value] = [
                'id' => (string) $created->id,
                'signature' => $signature,
                // Every rate this one has been, so an invoice charged at a
                // superseded rate still shows the name and percentage it had.
                'known' => [...($ref['known'] ?? []), (string) $created->id => $signature],
            ];
            $rate->gateway_refs = $refs;
            $rate->save();

            $ids[] = (string) $created->id;
        }

        return $ids;
    }

    /**
     * The user's Stripe customer in this mode, created on first use.
     */
    private function billingCustomer(Subscription $subscription): string
    {
        $user = $subscription->user ?? throw new GatewayException('The subscription has no user.');

        $existing = BillingCustomer::query()
            ->where('user_id', $user->id)
            ->where('gateway', Gateway::Stripe->value)
            ->where('mode', $this->mode->value)
            ->value('gateway_customer_id');

        if (is_string($existing)) {
            return $existing;
        }

        $customer = $this->call(fn () => $this->client()->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => (string) $user->id],
        ], ['idempotency_key' => CatalogueKey::for('customer', $user, $this->mode)]));

        try {
            // In its own transaction, like every insert here that may lose a
            // race: a savepoint when the caller holds one open, so PostgreSQL
            // does not abort the caller's transaction along with the insert.
            DB::transaction(fn () => BillingCustomer::query()->create([
                'user_id' => $user->id,
                'gateway' => Gateway::Stripe,
                'mode' => $this->mode,
                'gateway_customer_id' => (string) $customer->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            // A concurrent checkout recorded a customer first. Usually the
            // same one (the idempotency key returned it to both), but not
            // once the key has expired, so the recorded one is what is used.
            return (string) BillingCustomer::query()
                ->where('user_id', $user->id)
                ->where('gateway', Gateway::Stripe->value)
                ->where('mode', $this->mode->value)
                ->value('gateway_customer_id');
        }

        return (string) $customer->id;
    }

    /**
     * Every paid invoice on a subscription that took money, oldest first.
     *
     * @return list<GatewayInvoice>
     */
    private function paidInvoices(string $subscriptionId, Currency $currency): array
    {
        // Auto-paged: a subscription renewed for years can exceed one page
        // of invoices, and an invoice missing from this state is a payment
        // the ledger does not know was taken. Drained inside call(), since
        // every page after the first is its own request that can fail.
        $invoices = $this->call(fn (): array => iterator_to_array($this->client()->invoices->all([
            'subscription' => $subscriptionId,
            'status' => 'paid',
            'limit' => 100,
            'expand' => ['data.payments'],
        ])->autoPagingIterator(), preserve_keys: false));

        $paid = [];
        $knownRates = $this->knownTaxRates();

        foreach ($invoices as $object) {
            /** @var StripeObject $object */
            $invoice = $object->toArray();
            $amount = (int) ($invoice['amount_paid'] ?? 0);
            $paymentIntent = $this->invoicePaymentIntent($invoice);

            // A trial's $0 invoice took nothing, and one settled from the
            // customer's credit balance has no payment to refund.
            if ($amount <= 0 || $paymentIntent === null) {
                continue;
            }

            $paid[] = new GatewayInvoice(
                id: (string) $invoice['id'],
                paymentId: $paymentIntent,
                total: Money::of($amount, $currency),
                taxLines: $this->invoiceTaxLines($invoice, $currency, $knownRates),
                paidAt: $this->timestamp($invoice['status_transitions']['paid_at'] ?? $invoice['created'] ?? null) ?? CarbonImmutable::now(),
            );
        }

        return array_reverse($paid);
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function invoicePaymentIntent(array $invoice): ?string
    {
        foreach ($invoice['payments']['data'] ?? [] as $payment) {
            if (($payment['status'] ?? null) === 'paid' && is_string($payment['payment']['payment_intent'] ?? null)) {
                return $payment['payment']['payment_intent'];
            }
        }

        return null;
    }

    /**
     * Every Stripe TaxRate this installation has created in this mode, with
     * the name and percentage it was created with.
     *
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    private function knownTaxRates(): array
    {
        $known = [];

        foreach (TaxRate::query()->whereNotNull('gateway_refs')->get() as $rate) {
            foreach ($rate->gateway_refs[Gateway::Stripe->value][$this->mode->value]['known'] ?? [] as $id => $signature) {
                $parts = explode('|', (string) $signature, 2);
                // The registration number is the rate's current one: Stripe's
                // copy carries no such field, and it is the business's
                // registration for the tax, not a term of the charge.
                $known[(string) $id] = [$parts[0], $parts[1] ?? '0', $rate->registration_number];
            }
        }

        return $known;
    }

    /**
     * The taxes Stripe charged on an invoice, named as they were configured
     * when charged.
     *
     * @param  array<string, mixed>  $invoice
     * @param  array<string, array{0: string, 1: string, 2: ?string}>  $known
     * @return list<TaxLine>
     */
    private function invoiceTaxLines(array $invoice, Currency $currency, array $known): array
    {
        $amounts = [];

        foreach ($invoice['total_taxes'] ?? [] as $tax) {
            $id = (string) ($tax['tax_rate_details']['tax_rate'] ?? '');
            $amounts[$id] = ($amounts[$id] ?? 0) + max(0, (int) ($tax['amount'] ?? 0));
        }

        if ($amounts === []) {
            return [];
        }

        // Part of an invoice can be settled from the customer's credit
        // balance -- a downgrade's proration -- and that part took no money.
        // Only the tax on what was paid belongs on this payment, or the tax
        // would be overstated and could exceed the amount itself.
        $paid = (int) ($invoice['amount_paid'] ?? 0);
        $total = (int) ($invoice['total'] ?? $paid);
        $taxCharged = array_sum($amounts);
        $taxPaid = min($paid, $total > $paid && $total > 0
            ? intdiv($taxCharged * $paid * 2 + $total, $total * 2)
            : $taxCharged);

        $shares = $taxCharged > 0
            ? Money::of($taxPaid, $currency)->allocate($amounts)
            : array_map(fn (): Money => Money::zero($currency), $amounts);

        $lines = [];

        foreach ($shares as $id => $share) {
            [$name, $percentage, $registrationNumber] = $known[$id] ?? ['Tax', '0', null];
            $lines[] = new TaxLine($name, $percentage, $share, $registrationNumber);
        }

        return $lines;
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) ? CarbonImmutable::createFromTimestamp((int) $value) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
