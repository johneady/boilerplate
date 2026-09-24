<?php

namespace App\Payments\Drivers\Stripe;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\WebhookEvent;
use App\Payments\Contracts\DisputeDriver;
use App\Payments\Contracts\PaymentDriver;
use App\Payments\Contracts\RegistersWebhooks;
use App\Payments\Contracts\SubscriptionDriver;
use App\Payments\Contracts\WebhookDriver;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayDispute;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\GatewayStatus;
use App\Payments\Data\GatewayTransaction;
use App\Payments\Data\PaymentReference;
use App\Payments\Data\VerifiedWebhook;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\RefundStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\Money;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe Checkout for one-time payments, holds and refunds.
 *
 * The customer pays on Stripe's hosted Checkout page, so card data never
 * touches this application (PCI SAQ A). A Checkout Session creates a
 * PaymentIntent, which is what is captured, cancelled and refunded.
 *
 * Tax is sent as explicit line items computed by App\Payments\Tax\TaxCalculator
 * rather than Stripe TaxRate objects, so the amount charged is exactly the
 * amount this application calculated and showed the customer, to the cent.
 */
class StripeDriver implements DisputeDriver, PaymentDriver, RegistersWebhooks, SubscriptionDriver, WebhookDriver
{
    use ManagesStripeSubscriptions;

    /**
     * Event types that can change a payment, and where in the event object
     * the payment's identifiers are.
     */
    private const array CHECKOUT_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];

    private const array PAYMENT_INTENT_EVENTS = [
        'payment_intent.succeeded',
        'payment_intent.canceled',
        'payment_intent.payment_failed',
        'payment_intent.amount_capturable_updated',
    ];

    private const array CHARGE_EVENTS = [
        'charge.succeeded',
        'charge.captured',
        'charge.refunded',
        'charge.refund.updated',
        'refund.created',
        'refund.updated',
        'refund.failed',
    ];

    private const array DISPUTE_EVENTS = [
        'charge.dispute.created',
        'charge.dispute.updated',
        'charge.dispute.closed',
        'charge.dispute.funds_withdrawn',
        'charge.dispute.funds_reinstated',
    ];

    private ?StripeClient $client = null;

    /**
     * @param  array{secret_key: string, webhook_secret: string}  $credentials
     */
    public function __construct(
        private readonly GatewayMode $mode,
        private readonly array $credentials,
    ) {}

    public function createCheckout(Payment $payment, CheckoutUrls $urls): CheckoutSession
    {
        $currency = $payment->currency;

        $lineItems = [$this->lineItem($payment->description, $payment->subtotal, $currency)];

        foreach ($payment->taxLines() as $taxLine) {
            if ($taxLine->amount->isPositive()) {
                $lineItems[] = $this->lineItem($taxLine->label(), $taxLine->amount->amount, $currency);
            }
        }

        $session = $this->call(fn () => $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => $payment->uuid,
            'customer_email' => $payment->customer_email,
            'line_items' => $lineItems,
            'metadata' => ['payment_uuid' => $payment->uuid],
            'payment_intent_data' => [
                'capture_method' => $payment->capture_method === CaptureMethod::Manual ? 'manual' : 'automatic',
                'description' => mb_substr($payment->description, 0, 1000),
                'metadata' => ['payment_uuid' => $payment->uuid],
            ],
            'success_url' => $urls->returnUrl,
            'cancel_url' => $urls->cancelUrl,
            // Stripe accepts 30 minutes to 24 hours.
            'expires_at' => now()->addMinutes(max(30, min(1440, (int) config('payments.checkout_expiry_minutes'))))->getTimestamp(),
        ], ['idempotency_key' => $payment->gatewayKey('checkout')]));

        return new CheckoutSession((string) $session->id, (string) $session->url);
    }

    /**
     * Stripe Checkout completes the payment itself; there is nothing to do
     * when the customer returns.
     */
    public function completeCheckout(Payment $payment): void {}

    public function fetch(Payment $payment): GatewayPaymentState
    {
        // A subscription payment has no Checkout Session of its own: Stripe
        // Billing charged its PaymentIntent directly.
        if ($payment->gateway_checkout_id === null) {
            if ($payment->gateway_payment_id === null) {
                return new GatewayPaymentState(GatewayStatus::Open);
            }

            $intent = $this->call(fn () => $this->client()->paymentIntents->retrieve(
                $payment->gateway_payment_id,
                ['expand' => ['latest_charge']],
            ))->toArray();

            return $this->stateFromIntent($payment, $intent, sessionExpired: false);
        }

        $session = $this->call(fn () => $this->client()->checkout->sessions->retrieve(
            $payment->gateway_checkout_id,
            ['expand' => ['payment_intent.latest_charge']],
        ))->toArray();

        $intent = is_array($session['payment_intent'] ?? null) ? $session['payment_intent'] : null;
        $sessionExpired = ($session['status'] ?? null) === 'expired';

        if ($intent === null) {
            return new GatewayPaymentState($sessionExpired ? GatewayStatus::Expired : GatewayStatus::Open);
        }

        return $this->stateFromIntent($payment, $intent, $sessionExpired);
    }

    /**
     * @param  array<string, mixed>  $intent  A PaymentIntent with its latest_charge expanded.
     */
    private function stateFromIntent(Payment $payment, array $intent, bool $sessionExpired): GatewayPaymentState
    {
        $charge = is_array($intent['latest_charge'] ?? null) ? $intent['latest_charge'] : null;
        $currency = $payment->currency;

        return match ($intent['status'] ?? null) {
            'requires_capture' => new GatewayPaymentState(
                status: GatewayStatus::Authorized,
                paymentId: (string) $intent['id'],
                authorizationId: (string) $intent['id'],
                authorizationExpiresAt: $this->captureDeadline($intent, $charge),
            ),
            'succeeded' => new GatewayPaymentState(
                status: GatewayStatus::Captured,
                paymentId: (string) $intent['id'],
                charges: $charge !== null && (int) ($charge['amount_captured'] ?? 0) > 0
                    ? [new GatewayTransaction(
                        (string) $charge['id'],
                        Money::of((int) $charge['amount_captured'], $currency),
                        CarbonImmutable::createFromTimestamp((int) $charge['created']),
                    )]
                    : [],
                refunds: $this->refundsFor((string) $intent['id'], $currency),
            ),
            'canceled' => new GatewayPaymentState(
                status: GatewayStatus::Canceled,
                paymentId: (string) $intent['id'],
                failureReason: isset($intent['cancellation_reason']) ? (string) $intent['cancellation_reason'] : null,
            ),
            // requires_payment_method, requires_confirmation, requires_action,
            // processing: the customer is still paying, or a delayed method
            // (a bank debit) is settling. A declined card leaves the intent in
            // requires_payment_method -- the customer may still try another
            // card on the same Checkout page, so it is not a failure yet.
            default => new GatewayPaymentState(
                status: $sessionExpired ? GatewayStatus::Expired : GatewayStatus::Open,
                paymentId: (string) $intent['id'],
                failureReason: isset($intent['last_payment_error']['message']) ? (string) $intent['last_payment_error']['message'] : null,
            ),
        };
    }

    public function capture(Payment $payment, Money $amount): void
    {
        $this->call(fn () => $this->client()->paymentIntents->capture(
            (string) $payment->gateway_authorization_id,
            ['amount_to_capture' => $amount->amount],
            ['idempotency_key' => $payment->gatewayKey('capture')],
        ));
    }

    public function void(Payment $payment): void
    {
        $this->call(fn () => $this->client()->paymentIntents->cancel(
            (string) $payment->gateway_authorization_id,
            [],
            ['idempotency_key' => $payment->gatewayKey('void')],
        ));
    }

    public function refund(Payment $payment, Refund $refund): GatewayRefund
    {
        $stripeRefund = $this->call(fn () => $this->client()->refunds->create([
            'payment_intent' => (string) $payment->gateway_payment_id,
            'amount' => $refund->amount,
            'metadata' => ['payment_uuid' => $payment->uuid, 'refund_uuid' => $refund->uuid],
        ], ['idempotency_key' => $refund->idempotency_key]));

        return $this->mapRefund($stripeRefund->toArray(), $payment->currency);
    }

    public function expireCheckout(Payment $payment): void
    {
        if ($payment->gateway_checkout_id === null) {
            return;
        }

        try {
            $this->call(fn () => $this->client()->checkout->sessions->expire($payment->gateway_checkout_id));
        } catch (GatewayUnavailable $e) {
            throw $e;
        } catch (GatewayException) {
            // Already completed or already expired: either way, it can no
            // longer be paid through, which is all this was asking for. The
            // caller re-reads the state to find out which.
        }
    }

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        $secret = $this->credentials['webhook_secret'];

        if ($secret === '') {
            throw new InvalidWebhook("No Stripe webhook signing secret is configured for {$this->mode->label()} mode.");
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new InvalidWebhook('Stripe webhook signature verification failed: '.$e->getMessage(), 0, $e);
        }

        // A live endpoint must never accept a test event or the reverse, even
        // with a valid signature: the secrets could be pasted into the wrong
        // mode's fields.
        if ((bool) $event->livemode !== ($this->mode === GatewayMode::Live)) {
            throw new InvalidWebhook('Stripe event mode does not match the endpoint it was sent to.');
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        return new VerifiedWebhook((string) $event->id, (string) $event->type, $payload);
    }

    public function paymentReference(WebhookEvent $event): ?PaymentReference
    {
        /** @var array<string, mixed> $object */
        $object = $event->payload['data']['object'] ?? [];
        $uuid = $object['metadata']['payment_uuid'] ?? null;

        return match (true) {
            in_array($event->type, self::CHECKOUT_EVENTS, true) => new PaymentReference(
                uuid: is_string($uuid) ? $uuid : null,
                checkoutId: isset($object['id']) ? (string) $object['id'] : null,
            ),
            in_array($event->type, self::PAYMENT_INTENT_EVENTS, true) => new PaymentReference(
                uuid: is_string($uuid) ? $uuid : null,
                paymentId: isset($object['id']) ? (string) $object['id'] : null,
            ),
            in_array($event->type, self::CHARGE_EVENTS, true) => new PaymentReference(
                paymentId: isset($object['payment_intent']) && is_string($object['payment_intent']) ? $object['payment_intent'] : null,
            ),
            default => null,
        };
    }

    public function completesCheckout(WebhookEvent $event): bool
    {
        return false;
    }

    /**
     * The new endpoint is created before the old one at the same URL is
     * deleted, so no delivery is missed in between; an event both send is
     * stored once (webhook_events is unique on the event id).
     */
    public function registerWebhook(string $url): string
    {
        $created = $this->call(fn () => $this->client()->webhookEndpoints->create([
            'url' => $url,
            'enabled_events' => [
                ...self::CHECKOUT_EVENTS,
                ...self::PAYMENT_INTENT_EVENTS,
                ...self::CHARGE_EVENTS,
                ...self::SUBSCRIPTION_EVENTS,
                ...self::INVOICE_EVENTS,
                ...self::DISPUTE_EVENTS,
            ],
            // Events arrive in the shape the rest of this driver reads.
            'api_version' => (string) config('payments.stripe.api_version'),
            'description' => mb_substr(app(Settings::class)->businessName().' payments', 0, 5000),
        ]));

        foreach ($this->call(fn () => $this->client()->webhookEndpoints->all(['limit' => 100]))->data as $endpoint) {
            if ($endpoint->url === $url && $endpoint->id !== $created->id) {
                $this->call(fn () => $this->client()->webhookEndpoints->delete((string) $endpoint->id));
            }
        }

        return (string) $created->secret;
    }

    public function disputeReference(WebhookEvent $event): ?string
    {
        $id = $event->payload['data']['object']['id'] ?? null;

        return str_starts_with($event->type, 'charge.dispute.') && is_string($id) ? $id : null;
    }

    public function fetchDispute(string $disputeId): GatewayDispute
    {
        $dispute = $this->call(fn () => $this->client()->disputes->retrieve($disputeId))->toArray();
        $currency = Currency::tryFrom(strtoupper((string) ($dispute['currency'] ?? '')));
        $dueBy = $dispute['evidence_details']['due_by'] ?? null;

        return new GatewayDispute(
            id: (string) $dispute['id'],
            status: match ($dispute['status'] ?? null) {
                'needs_response', 'warning_needs_response' => DisputeStatus::NeedsResponse,
                'under_review', 'warning_under_review' => DisputeStatus::UnderReview,
                // An inquiry closed without a chargeback, or one prevented
                // before it became one, cost nothing.
                'won', 'warning_closed', 'prevented' => DisputeStatus::Won,
                'lost' => DisputeStatus::Lost,
                default => DisputeStatus::UnderReview,
            },
            payment: new PaymentReference(
                paymentId: is_string($dispute['payment_intent'] ?? null) ? $dispute['payment_intent'] : null,
                transactionId: is_string($dispute['charge'] ?? null) ? $dispute['charge'] : null,
            ),
            amount: $currency !== null ? Money::of((int) ($dispute['amount'] ?? 0), $currency) : null,
            reason: isset($dispute['reason']) ? (string) $dispute['reason'] : null,
            evidenceDueBy: is_numeric($dueBy) ? CarbonImmutable::createFromTimestamp((int) $dueBy) : null,
        );
    }

    /**
     * Every Stripe refund is on its PaymentIntent, which fetch() reads.
     */
    public function refundInEvent(WebhookEvent $event, Payment $payment): ?GatewayRefund
    {
        return null;
    }

    /**
     * Every refund on a PaymentIntent, including ones made in the Stripe
     * dashboard.
     *
     * @return list<GatewayRefund>
     */
    private function refundsFor(string $paymentIntentId, Currency $currency): array
    {
        $refunds = $this->call(fn () => $this->client()->refunds->all(['payment_intent' => $paymentIntentId, 'limit' => 100]));

        return array_values(array_map(
            fn (StripeObject $refund): GatewayRefund => $this->mapRefund($refund->toArray(), $currency),
            $refunds->data,
        ));
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function mapRefund(array $refund, Currency $currency): GatewayRefund
    {
        $reference = $refund['metadata']['refund_uuid'] ?? null;

        return new GatewayRefund(
            id: (string) $refund['id'],
            amount: Money::of((int) $refund['amount'], $currency),
            status: match ($refund['status'] ?? null) {
                'succeeded' => RefundStatus::Succeeded,
                'failed', 'canceled' => RefundStatus::Failed,
                default => RefundStatus::Pending,
            },
            occurredAt: CarbonImmutable::createFromTimestamp((int) ($refund['created'] ?? time())),
            reference: is_string($reference) ? $reference : null,
            failureReason: isset($refund['failure_reason']) ? (string) $refund['failure_reason'] : null,
        );
    }

    /**
     * When an authorization lapses. Stripe reports it on the card charge; the
     * seven-day default covers any payment method that does not.
     *
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>|null  $charge
     */
    private function captureDeadline(array $intent, ?array $charge): CarbonImmutable
    {
        $captureBefore = $charge['payment_method_details']['card']['capture_before'] ?? null;

        return is_numeric($captureBefore)
            ? CarbonImmutable::createFromTimestamp((int) $captureBefore)
            : CarbonImmutable::createFromTimestamp((int) ($intent['created'] ?? time()))->addDays(7);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineItem(string $name, int $amount, Currency $currency): array
    {
        return [
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency->stripeCode(),
                'unit_amount' => $amount,
                'product_data' => ['name' => mb_substr($name, 0, 250)],
            ],
        ];
    }

    private function client(): StripeClient
    {
        if ($this->credentials['secret_key'] === '') {
            throw new GatewayException("No Stripe secret key is configured for {$this->mode->label()} mode.");
        }

        ApiRequestor::setHttpClient(new LaravelHttpClient);

        return $this->client ??= new StripeClient([
            'api_key' => $this->credentials['secret_key'],
            'stripe_version' => (string) config('payments.stripe.api_version'),
        ]);
    }

    /**
     * Run a Stripe call, translating its failures into the module's two kinds:
     * a definitive refusal, or an unknown outcome to retry.
     *
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    private function call(Closure $call): mixed
    {
        try {
            return $call();
        } catch (ApiConnectionException|RateLimitException $e) {
            throw new GatewayUnavailable('Stripe could not be reached: '.$e->getMessage(), 0, $e);
        } catch (ApiErrorException $e) {
            if ($e->getHttpStatus() === null || $e->getHttpStatus() >= 500) {
                throw new GatewayUnavailable('Stripe failed to process the request: '.$e->getMessage(), 0, $e);
            }

            throw new GatewayException($e->getMessage(), 0, $e);
        }
    }
}
