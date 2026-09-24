<?php

namespace App\Payments\Drivers\PayPal;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\WebhookEvent;
use App\Payments\Contracts\PaymentDriver;
use App\Payments\Contracts\WebhookDriver;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\GatewayStatus;
use App\Payments\Data\GatewayTransaction;
use App\Payments\Data\PaymentReference;
use App\Payments\Data\VerifiedWebhook;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\TransactionType;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\Money;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * PayPal Checkout (Orders v2) for one-time payments, holds and refunds.
 *
 * Unlike Stripe, a PayPal order is not paid when the customer approves it:
 * the application must then capture it (or authorize it, for a hold). That
 * happens in completeCheckout(), called when the customer returns -- and from
 * the CHECKOUT.ORDER.APPROVED webhook, for a customer who approved and then
 * closed the tab before coming back.
 *
 * Requires a PayPal Business account: PayPal issues live REST credentials to
 * Business accounts only.
 */
class PayPalDriver implements PaymentDriver, WebhookDriver
{
    public function __construct(private readonly PayPalClient $client) {}

    public function createCheckout(Payment $payment, CheckoutUrls $urls): CheckoutSession
    {
        $currency = $payment->currency->value;
        $description = mb_substr($payment->description, 0, 127);

        $unit = [
            'reference_id' => $payment->uuid,
            'custom_id' => $payment->uuid,
            'invoice_id' => $payment->uuid,
            'description' => $description,
            'amount' => [
                'currency_code' => $currency,
                'value' => $payment->total()->toDecimal(),
                'breakdown' => [
                    'item_total' => ['currency_code' => $currency, 'value' => $payment->subtotalMoney()->toDecimal()],
                    'tax_total' => ['currency_code' => $currency, 'value' => $payment->taxTotalMoney()->toDecimal()],
                ],
            ],
            'items' => [[
                'name' => $description,
                'quantity' => '1',
                'unit_amount' => ['currency_code' => $currency, 'value' => $payment->subtotalMoney()->toDecimal()],
                'tax' => ['currency_code' => $currency, 'value' => $payment->taxTotalMoney()->toDecimal()],
            ]],
        ];

        $order = $this->client->post('/v2/checkout/orders', [
            'intent' => $payment->capture_method === CaptureMethod::Manual ? 'AUTHORIZE' : 'CAPTURE',
            'purchase_units' => [$unit],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => mb_substr(app(Settings::class)->businessName(), 0, 127),
                        'user_action' => 'PAY_NOW',
                        'shipping_preference' => 'NO_SHIPPING',
                        'return_url' => $urls->returnUrl,
                        'cancel_url' => $urls->cancelUrl,
                    ],
                ],
            ],
        ], $payment->gatewayKey('checkout'));

        $approveUrl = $this->linkHref($order, fn (string $rel, string $href): bool => in_array($rel, ['payer-action', 'approve'], true));

        if (! isset($order['id']) || ! is_string($approveUrl)) {
            throw new GatewayException('PayPal did not return an order to approve.');
        }

        return new CheckoutSession((string) $order['id'], $approveUrl);
    }

    /**
     * Capture (or authorize) an order the customer has approved.
     *
     * Only for a payment still pending: once a payment has expired, the
     * approval it may still hold at PayPal is deliberately never captured.
     */
    public function completeCheckout(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Pending || $payment->gateway_checkout_id === null) {
            return;
        }

        $order = $this->client->get("/v2/checkout/orders/{$payment->gateway_checkout_id}");

        if (($order['status'] ?? null) !== 'APPROVED') {
            return;
        }

        $action = $payment->capture_method === CaptureMethod::Manual ? 'authorize' : 'capture';

        $this->client->post(
            "/v2/checkout/orders/{$payment->gateway_checkout_id}/{$action}",
            [],
            $payment->gatewayKey("order-{$action}"),
        );
    }

    public function fetch(Payment $payment): GatewayPaymentState
    {
        if ($payment->gateway_checkout_id === null) {
            return new GatewayPaymentState(GatewayStatus::Open);
        }

        $order = $this->client->get("/v2/checkout/orders/{$payment->gateway_checkout_id}");
        $payments = $order['purchase_units'][0]['payments'] ?? [];
        $currency = $payment->currency;

        $charges = [];
        $captureFailure = null;

        foreach ($payments['captures'] ?? [] as $capture) {
            $status = $capture['status'] ?? null;

            // PARTIALLY_REFUNDED and REFUNDED captures still took the money;
            // the refunds are recorded separately below.
            if (in_array($status, ['COMPLETED', 'PARTIALLY_REFUNDED', 'REFUNDED'], true)) {
                $charges[] = new GatewayTransaction(
                    (string) $capture['id'],
                    Money::fromDecimal((string) $capture['amount']['value'], $currency),
                    CarbonImmutable::parse((string) ($capture['create_time'] ?? 'now')),
                );
            } elseif (in_array($status, ['DECLINED', 'FAILED'], true)) {
                $captureFailure = (string) ($capture['status_details']['reason'] ?? $status);
            }
        }

        $refunds = array_map(fn (array $refund): GatewayRefund => $this->mapRefund($refund, $currency), $payments['refunds'] ?? []);

        if ($charges !== []) {
            return new GatewayPaymentState(GatewayStatus::Captured, (string) $order['id'], charges: $charges, refunds: array_values($refunds));
        }

        if ($captureFailure !== null) {
            return new GatewayPaymentState(GatewayStatus::Failed, (string) $order['id'], failureReason: $captureFailure);
        }

        $authorization = $payments['authorizations'][0] ?? null;

        if (is_array($authorization)) {
            return new GatewayPaymentState(
                status: match ($authorization['status'] ?? null) {
                    'CREATED', 'PENDING' => GatewayStatus::Authorized,
                    'VOIDED' => GatewayStatus::Canceled,
                    'EXPIRED' => GatewayStatus::Expired,
                    'DENIED' => GatewayStatus::Failed,
                    default => GatewayStatus::Open,
                },
                paymentId: (string) $order['id'],
                authorizationId: (string) $authorization['id'],
                authorizationExpiresAt: isset($authorization['expiration_time']) ? CarbonImmutable::parse((string) $authorization['expiration_time']) : null,
            );
        }

        return new GatewayPaymentState(
            status: ($order['status'] ?? null) === 'VOIDED' ? GatewayStatus::Canceled : GatewayStatus::Open,
            paymentId: (string) $order['id'],
        );
    }

    public function capture(Payment $payment, Money $amount): void
    {
        $this->client->post("/v2/payments/authorizations/{$payment->gateway_authorization_id}/capture", [
            'amount' => ['currency_code' => $amount->currency->value, 'value' => $amount->toDecimal()],
            'final_capture' => true,
            'invoice_id' => $payment->uuid,
        ], $payment->gatewayKey('capture'));
    }

    public function void(Payment $payment): void
    {
        $this->client->post("/v2/payments/authorizations/{$payment->gateway_authorization_id}/void", [], $payment->gatewayKey('void'));
    }

    public function refund(Payment $payment, Refund $refund): GatewayRefund
    {
        $captureId = $payment->transactions()
            ->where('type', TransactionType::Charge->value)
            ->orderBy('id')
            ->value('gateway_transaction_id');

        if (! is_string($captureId)) {
            throw new GatewayException('This PayPal payment has no capture to refund.');
        }

        $body = [
            'amount' => ['currency_code' => $refund->currency->value, 'value' => $refund->money()->toDecimal()],
            // Echoed back on the refund, which is how a refund whose response
            // was lost is matched to its row when the order is next fetched.
            'invoice_id' => $refund->uuid,
            'custom_id' => $refund->uuid,
        ];

        if (filled($refund->reason)) {
            $body['note_to_payer'] = mb_substr((string) $refund->reason, 0, 255);
        }

        return $this->mapRefund(
            $this->client->post("/v2/payments/captures/{$captureId}/refund", $body, $refund->idempotency_key),
            $payment->currency,
            // A minimal representation omits the amount; it is the one asked for.
            fallbackAmount: $refund->money(),
        );
    }

    /**
     * PayPal orders cannot be expired through the API. An abandoned order is
     * instead never captured: completeCheckout() refuses anything no longer
     * pending.
     */
    public function expireCheckout(Payment $payment): void {}

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        if ($this->client->webhookId === '') {
            throw new InvalidWebhook('No PayPal webhook ID is configured for this mode.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['id'], $payload['event_type'])) {
            throw new InvalidWebhook('The PayPal webhook body is not an event.');
        }

        $headers = [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        if (in_array(null, $headers, true)) {
            throw new InvalidWebhook('The PayPal webhook is missing its signature headers.');
        }

        try {
            $result = $this->client->post('/v1/notifications/verify-webhook-signature', [
                ...$headers,
                'webhook_id' => $this->client->webhookId,
                'webhook_event' => $payload,
            ]);
        } catch (GatewayException $e) {
            throw new InvalidWebhook('PayPal could not verify the webhook: '.$e->getMessage(), 0, $e);
        }

        if (($result['verification_status'] ?? null) !== 'SUCCESS') {
            throw new InvalidWebhook('PayPal webhook signature verification failed.');
        }

        return new VerifiedWebhook((string) $payload['id'], (string) $payload['event_type'], $payload);
    }

    public function paymentReference(WebhookEvent $event): ?PaymentReference
    {
        /** @var array<string, mixed> $resource */
        $resource = $event->payload['resource'] ?? [];

        if (str_starts_with($event->type, 'CHECKOUT.ORDER.')) {
            return new PaymentReference(checkoutId: isset($resource['id']) ? (string) $resource['id'] : null);
        }

        if (! str_starts_with($event->type, 'PAYMENT.CAPTURE.') && ! str_starts_with($event->type, 'PAYMENT.AUTHORIZATION.')) {
            return null;
        }

        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        // A refund resource names the capture it refunded in its "up" link.
        $captureUrl = $this->linkHref($resource, fn (string $rel, string $href): bool => $rel === 'up' && str_contains($href, '/captures/'));

        return new PaymentReference(
            // custom_id carries the Payment uuid on captures and authorizations.
            // On a refund it carries the Refund uuid, which simply matches no
            // payment, so the order id and capture link are the fallbacks.
            uuid: isset($resource['custom_id']) ? (string) $resource['custom_id'] : null,
            checkoutId: is_string($orderId) ? $orderId : null,
            transactionId: $captureUrl !== null ? basename($captureUrl) : null,
        );
    }

    public function completesCheckout(WebhookEvent $event): bool
    {
        return $event->type === 'CHECKOUT.ORDER.APPROVED';
    }

    /**
     * The href of the first HATEOAS link on a PayPal resource matching a test.
     *
     * @param  array<string, mixed>  $resource
     * @param  \Closure(string, string): bool  $matches  Given the link's rel and href.
     */
    private function linkHref(array $resource, \Closure $matches): ?string
    {
        $links = $resource['links'] ?? [];

        foreach (is_array($links) ? $links : [] as $link) {
            $rel = is_array($link) ? (string) ($link['rel'] ?? '') : '';
            $href = is_array($link) ? (string) ($link['href'] ?? '') : '';

            if ($href !== '' && $matches($rel, $href)) {
                return $href;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function mapRefund(array $refund, Currency $currency, ?Money $fallbackAmount = null): GatewayRefund
    {
        $reference = $refund['custom_id'] ?? $refund['invoice_id'] ?? null;

        if (! isset($refund['id'])) {
            throw new GatewayException('PayPal did not return the refund.');
        }

        return new GatewayRefund(
            id: (string) $refund['id'],
            amount: isset($refund['amount']['value'])
                ? Money::fromDecimal((string) $refund['amount']['value'], $currency)
                : ($fallbackAmount ?? throw new GatewayException('PayPal did not report the refund amount.')),
            status: match ($refund['status'] ?? null) {
                'COMPLETED' => RefundStatus::Succeeded,
                'FAILED', 'CANCELLED' => RefundStatus::Failed,
                default => RefundStatus::Pending,
            },
            occurredAt: CarbonImmutable::parse((string) ($refund['create_time'] ?? 'now')),
            reference: is_string($reference) ? $reference : null,
            failureReason: isset($refund['status_details']['reason']) ? (string) $refund['status_details']['reason'] : null,
        );
    }
}
