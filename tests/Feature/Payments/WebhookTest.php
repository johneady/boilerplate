<?php

use App\Jobs\ProcessWebhookEvent;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\WebhookEvent;
use App\Notifications\Payments\WebhookProcessingFailed;
use App\Notifications\Payments\WebhookSignatureRejected;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\WebhookEventStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\PaymentFixtures;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable([
        'stripe_enabled' => true,
        'stripe_sandbox_secret_key' => 'sk_test_51Example',
        'stripe_sandbox_webhook_secret' => 'whsec_example',
        'paypal_enabled' => true,
        'paypal_sandbox_client_id' => 'client-id',
        'paypal_sandbox_client_secret' => 'client-secret',
        'paypal_sandbox_webhook_id' => 'WH-ID-123',
        'ops_alert_email' => 'ops@example.test',
    ]);

    Http::preventStrayRequests();
});

/**
 * Deliver a Stripe event to the sandbox endpoint, signed as Stripe would.
 *
 * @param  array<string, mixed>  $event
 */
function deliverStripe(array $event, string $secret = 'whsec_example', string $mode = 'sandbox'): TestResponse
{
    $payload = (string) json_encode($event);

    return test()->call('POST', route('payments.webhook', ['gateway' => 'stripe', 'mode' => $mode]), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => PaymentFixtures::stripeSignature($payload, $secret),
    ], $payload);
}

/**
 * Deliver a PayPal event, with the signature headers PayPal sends.
 *
 * @param  array<string, mixed>  $event
 * @param  array<string, string>  $headers  Server variables replacing the genuine ones.
 */
function deliverPayPal(array $event, array $headers = []): TestResponse
{
    return test()->call('POST', route('payments.webhook', ['gateway' => 'paypal', 'mode' => 'sandbox']), [], [], [], [...[
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        'HTTP_PAYPAL_CERT_URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-360caa42',
        'HTTP_PAYPAL_TRANSMISSION_ID' => '69cd13f0-d67a-11e5-baa3-778b53f4ae55',
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'lmI95Jx3Y9nhR5SJWlHVIWpg4AgFk7n9bCHSRxbrd8A9zrhdu2rMyFrmz+Zjh3s3boXB07VXCXUZy/UFzUlnGJn0wDugt7FlSvdKeIJenLpKUUz1jgIhNtWAiH8/K8I+Ttf/Na7+E0Hg2NRbfDjbSyw4g/ovbxWnDg3m+PYs9tqK8ZdqeU/o5aM1JWiRw9qlTQOaIG3r+HOzKmNIhpd5UE4rJDXKVTmPGFYDRJx8hMe8iCK5o0J0Eg0Xcnea2Z5JOANyOh3Z1YiB1Zx6jR48fDkx9ENm7Hg06IR8M4sVLajs8/IOA0nNSIi/n8xLXBONQNt8PJG+aNxbu8e0JtTPfQ==',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601ZuluString(),
    ], ...$headers], (string) json_encode($event));
}

/**
 * A pending Stripe payment whose session the gateway now reports as paid.
 */
function paidStripePayment(): Payment
{
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ($request->method().' '.$path) {
            'POST /v1/checkout/sessions' => Http::response(PaymentFixtures::load('stripe/checkout_session_open')),
            'GET /v1/checkout/sessions/cs_test_a1B2c3' => Http::response(PaymentFixtures::load('stripe/checkout_session_paid')),
            'GET /v1/refunds' => Http::response(PaymentFixtures::load('stripe/refund_list')),
            default => Http::response(['error' => ['message' => "Unexpected {$path}"]], 500),
        };
    });

    return Payments::checkout(PaymentLink::factory()->costing(11300)->create(), Gateway::Stripe);
}

test('a verified Stripe event is stored, acknowledged and reconciles the payment from the API', function () {
    $payment = paidStripePayment();

    deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed'))->assertOk();

    expect(WebhookEvent::sole())
        ->event_id->toBe('evt_test_E7v')
        ->status->toBe(WebhookEventStatus::Processed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('one address flooding the endpoint is throttled without shutting out the gateway', function () {
    Notification::fake();
    Queue::fake();
    $event = PaymentFixtures::load('stripe/event_checkout_session_completed');

    for ($i = 0; $i < 120; $i++) {
        deliverStripe($event, 'whsec_forged');
    }

    deliverStripe($event, 'whsec_forged')->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
    deliverStripe($event)->assertOk();
});

test('a redelivered event is acknowledged but processed once', function () {
    paidStripePayment();
    $event = PaymentFixtures::load('stripe/event_checkout_session_completed');

    deliverStripe($event)->assertOk();
    deliverStripe($event)->assertOk();

    expect(WebhookEvent::count())->toBe(1);
    Http::assertSentCount(3); // create session, then ONE retrieve and refund list
});

test('events arriving out of order still leave the payment as the gateway has it', function () {
    $payment = paidStripePayment();

    // The refund notice first, then the completion it depends on: each
    // re-reads the payment, so order does not matter.
    deliverStripe(PaymentFixtures::load('stripe/event_charge_refunded'))->assertOk();
    deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed'))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->transactions()->count())->toBe(1);
});

test('a delivery that fails verification is refused and stored nowhere', function (Closure $deliver) {
    $deliver()->assertStatus(400);

    expect(WebhookEvent::count())->toBe(0);
})->with([
    'wrong signing secret' => fn () => deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed'), 'whsec_forged'),
    'live event at the sandbox endpoint' => fn () => deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed', ['livemode' => true])),
]);

test('an endpoint never given a signing secret answers as if it did not exist', function () {
    deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed'), mode: 'live')->assertNotFound();

    expect(WebhookEvent::count())->toBe(0);
});

test('webhooks keep completing payments in flight after payments are switched off', function () {
    $payment = paidStripePayment();
    Payments::enable(['payments_enabled' => false]);

    deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed'))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('a stored event whose processing never started is picked up by the redelivery', function () {
    paidStripePayment();
    Queue::fake([ProcessWebhookEvent::class]);
    $event = PaymentFixtures::load('stripe/event_checkout_session_completed');

    deliverStripe($event)->assertOk();
    deliverStripe($event)->assertOk();

    expect(WebhookEvent::count())->toBe(1);
    Queue::assertPushedTimes(ProcessWebhookEvent::class, 2);
});

test('a stored event nobody processed is dispatched again by the stale reconciliation', function () {
    Queue::fake([ProcessWebhookEvent::class]);
    $event = WebhookEvent::factory()->create(['created_at' => now()->subHour()]);

    $this->artisan('payments:reconcile-stale')->assertSuccessful();

    Queue::assertPushed(ProcessWebhookEvent::class, fn (ProcessWebhookEvent $job): bool => $job->webhookEventId === $event->id);
});

test('an unsigned post is refused', function () {
    $this->postJson(route('payments.webhook', ['gateway' => 'stripe', 'mode' => 'sandbox']), PaymentFixtures::load('stripe/event_checkout_session_completed'))
        ->assertStatus(400);
});

test('a webhook for a gateway that sends none, or an unknown mode, is not found', function (string $gateway, string $mode) {
    $this->post(route('payments.webhook', ['gateway' => $gateway, 'mode' => $mode]))->assertNotFound();
})->with([['demo', 'sandbox'], ['manual', 'live'], ['stripe', 'staging']]);

test('an event this application does not act on is stored and marked ignored', function () {
    deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed', ['type' => 'customer.created']))->assertOk();

    expect(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Ignored);
});

test('a sandbox event can never touch a live payment', function () {
    $live = Payment::factory()->gateway(Gateway::Stripe)->live()->create(['gateway_checkout_id' => 'cs_test_a1B2c3']);

    deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed'))->assertOk();

    expect(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Ignored)
        ->and($live->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('operators are told once when deliveries keep failing verification', function () {
    Notification::fake();

    for ($i = 0; $i < 8; $i++) {
        deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed'), 'whsec_forged');
    }

    Notification::assertSentOnDemandTimes(WebhookSignatureRejected::class, 1);
});

test('an event that exhausts its retries is marked failed and operators are told', function () {
    Notification::fake();
    $event = WebhookEvent::factory()->create();

    (new ProcessWebhookEvent($event->id))->failed(new RuntimeException('Stripe could not be reached.'));

    expect($event->fresh())
        ->status->toBe(WebhookEventStatus::Failed)
        ->error->toBe('Stripe could not be reached.');

    Notification::assertSentOnDemand(WebhookProcessingFailed::class);
});

test('a PayPal approval whose customer never came back is captured from the webhook', function () {
    $state = 'paypal/order_approved';

    Http::fake(function (Request $request) use (&$state) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ($request->method().' '.$path) {
            'POST /v1/oauth2/token' => Http::response(PaymentFixtures::load('paypal/token')),
            'POST /v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            'POST /v2/checkout/orders' => Http::response(PaymentFixtures::load('paypal/order_created')),
            'GET /v2/checkout/orders/5O190127TN364715T' => Http::response(PaymentFixtures::load($state)),
            'POST /v2/checkout/orders/5O190127TN364715T/capture' => (function () use (&$state) {
                $state = 'paypal/order_completed';

                return Http::response(PaymentFixtures::load($state), 201);
            })(),
            default => Http::response(['name' => 'UNEXPECTED'], 500),
        };
    });

    $payment = Payments::checkout(PaymentLink::factory()->costing(11300)->create(), Gateway::PayPal);

    deliverPayPal(PaymentFixtures::load('paypal/event_order_approved'))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/verify-webhook-signature')
        && $request->data()['webhook_id'] === 'WH-ID-123'
        && $request->data()['webhook_event']['id'] === 'WH-2WR32451HC0233532-67976317FL4543714');
});

test('a PayPal delivery that cannot be genuine is refused without asking PayPal', function (array $headers) {
    Http::fake();

    deliverPayPal(PaymentFixtures::load('paypal/event_order_approved'), $headers)->assertStatus(400);

    Http::assertNothingSent();
    expect(WebhookEvent::count())->toBe(0);
})->with([
    'a certificate off PayPal\'s host' => [['HTTP_PAYPAL_CERT_URL' => 'https://paypal.com.example.test/certs/CERT-1']],
    'a certificate over plain http' => [['HTTP_PAYPAL_CERT_URL' => 'http://api.sandbox.paypal.com/v1/notifications/certs/CERT-360caa42']],
    'sent eleven minutes ago' => [fn (): array => ['HTTP_PAYPAL_TRANSMISSION_TIME' => now()->subMinutes(11)->toIso8601ZuluString()]],
    'an unreadable time' => [['HTTP_PAYPAL_TRANSMISSION_TIME' => 'yesterday-ish']],
]);

test('a PayPal event PayPal does not verify is refused', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(PaymentFixtures::load('paypal/token')),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
    ]);

    deliverPayPal(PaymentFixtures::load('paypal/event_order_approved'))->assertStatus(400);

    expect(WebhookEvent::count())->toBe(0);
});
