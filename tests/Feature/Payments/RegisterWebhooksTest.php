<?php

use App\Filament\Pages\ManageSettings;
use App\Models\User;
use App\Notifications\Payments\PaymentCredentialsChanged;
use App\Payments\Actions\RegisterWebhooks;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\GatewayFakes;
use Tests\Support\PaymentFixtures;
use Tests\Support\Payments;

const STRIPE_ENDPOINT = 'https://shop.example.com/webhooks/stripe/sandbox';
const PAYPAL_ENDPOINT = 'https://shop.example.com/webhooks/paypal/sandbox';

beforeEach(function () {
    Payments::enable([
        'stripe_enabled' => true,
        'stripe_sandbox_secret_key' => 'sk_test_51Example',
        'paypal_enabled' => true,
        'paypal_sandbox_client_id' => 'client-id',
        'paypal_sandbox_client_secret' => 'client-secret',
        'ops_alert_email' => 'ops@example.test',
    ]);

    servedFrom('https://shop.example.com');
    Notification::fake();
});

/**
 * Publish the site at this address (APP_URL).
 */
function servedFrom(string $root): void
{
    config()->set('app.url', $root);
}

function fakeStripeEndpoints(): void
{
    GatewayFakes::stripe([
        'POST /v1/webhook_endpoints' => PaymentFixtures::load('stripe/webhook_endpoint'),
        'GET /v1/webhook_endpoints' => ['object' => 'list', 'has_more' => false, 'data' => [
            PaymentFixtures::load('stripe/webhook_endpoint', ['id' => 'we_test_Old']),
            PaymentFixtures::load('stripe/webhook_endpoint', ['id' => 'we_test_OtherSite', 'url' => 'https://other.example.com/hooks']),
            PaymentFixtures::load('stripe/webhook_endpoint'),
        ]],
        'DELETE /v1/webhook_endpoints/we_test_Old' => ['id' => 'we_test_Old', 'deleted' => true],
    ]);
}

test('connecting Stripe creates an endpoint for every event, stores its secret, and retires the old one', function () {
    fakeStripeEndpoints();

    $key = app(RegisterWebhooks::class)->handle(Gateway::Stripe, GatewayMode::Sandbox);

    expect($key)->toBe(SettingKey::StripeSandboxWebhookSecret)
        ->and(app(Settings::class)->string(SettingKey::StripeSandboxWebhookSecret))->toBe('whsec_test_Registered');

    Http::assertSent(function (Request $request): bool {
        $body = GatewayFakes::stripeBody($request);

        return GatewayFakes::is($request, 'POST /v1/webhook_endpoints')
            && $body['url'] === STRIPE_ENDPOINT
            && $body['api_version'] === config('payments.stripe.api_version')
            && count(array_intersect(['checkout.session.completed', 'invoice.paid', 'customer.subscription.updated', 'charge.dispute.created', 'charge.refunded'], $body['enabled_events'])) === 5;
    });
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'DELETE /v1/webhook_endpoints/we_test_Old'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'we_test_OtherSite') || str_contains($request->url(), 'we_test_New'));
});

test('connecting PayPal updates the existing webhook in place, keeping its id', function () {
    GatewayFakes::paypal([
        'GET /v1/notifications/webhooks' => ['webhooks' => [['id' => 'WH-EXISTING', 'url' => PAYPAL_ENDPOINT, 'event_types' => []]]],
        'PATCH /v1/notifications/webhooks/WH-EXISTING' => ['id' => 'WH-EXISTING', 'url' => PAYPAL_ENDPOINT],
    ]);

    app(RegisterWebhooks::class)->handle(Gateway::PayPal, GatewayMode::Sandbox);

    expect(app(Settings::class)->string(SettingKey::PayPalSandboxWebhookId))->toBe('WH-EXISTING');
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'PATCH /v1/notifications/webhooks/WH-EXISTING')
        && $request->data()[0]['path'] === '/event_types'
        && in_array(['name' => 'CUSTOMER.DISPUTE.CREATED'], $request->data()[0]['value'], true));
});

test('connecting PayPal for the first time creates the webhook', function () {
    GatewayFakes::paypal([
        'GET /v1/notifications/webhooks' => ['webhooks' => []],
        'POST /v1/notifications/webhooks' => fn () => Http::response(['id' => 'WH-NEW', 'url' => PAYPAL_ENDPOINT], 201),
    ]);

    app(RegisterWebhooks::class)->handle(Gateway::PayPal, GatewayMode::Sandbox);

    expect(app(Settings::class)->string(SettingKey::PayPalSandboxWebhookId))->toBe('WH-NEW');
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/notifications/webhooks') && $request->data()['url'] === PAYPAL_ENDPOINT);
});

test('webhooks cannot be connected from an address the gateway cannot reach', function (string $root) {
    servedFrom($root);

    app(RegisterWebhooks::class)->handle(Gateway::Stripe, GatewayMode::Sandbox);
})->with(['http://shop.example.com', 'https://localhost', 'https://boilerplate.test'])->throws(PaymentNotAllowed::class);

test('the settings button needs the password and tells operators', function () {
    fakeStripeEndpoints();
    $admin = User::factory()->admin()->create(['password' => 'correct-password']);
    $this->actingAs($admin);

    Livewire::test(ManageSettings::class)
        ->callAction('connectWebhooks', ['gateway' => 'stripe', 'current_password' => 'wrong'])
        ->assertHasActionErrors(['current_password']);

    expect(app(Settings::class)->string(SettingKey::StripeSandboxWebhookSecret))->toBe('');

    Livewire::test(ManageSettings::class)
        ->callAction('connectWebhooks', ['gateway' => 'stripe', 'current_password' => 'correct-password'])
        ->assertHasNoActionErrors()
        ->assertNotified(__('payments.settings.webhooks_connected', ['gateway' => 'Stripe']));

    expect(app(Settings::class)->string(SettingKey::StripeSandboxWebhookSecret))->toBe('whsec_test_Registered');
    Notification::assertSentOnDemand(PaymentCredentialsChanged::class);
});

test('the endpoint is the published address, whatever host the administrator is using', function () {
    fakeStripeEndpoints();
    $this->get('http://admin.internal/');

    app(RegisterWebhooks::class)->handle(Gateway::Stripe, GatewayMode::Sandbox);

    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/webhook_endpoints')
        && GatewayFakes::stripeBody($request)['url'] === STRIPE_ENDPOINT);
});

test('the new Stripe secret is kept even when clearing out the old endpoint fails', function () {
    GatewayFakes::stripe([
        'POST /v1/webhook_endpoints' => PaymentFixtures::load('stripe/webhook_endpoint'),
        'GET /v1/webhook_endpoints' => fn () => Http::response(['error' => ['message' => 'Rate limited']], 429),
    ]);

    expect(fn () => app(RegisterWebhooks::class)->handle(Gateway::Stripe, GatewayMode::Sandbox))->toThrow(GatewayException::class)
        ->and(app(Settings::class)->string(SettingKey::StripeSandboxWebhookSecret))->toBe('whsec_test_Registered');
});
