<?php

use App\Filament\Resources\Disputes\DisputeResource;
use App\Filament\Resources\Disputes\Pages\ListDisputes;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\Payments\DisputeOpened;
use App\Payments\Actions\RefundPayment;
use App\Payments\Enums\Currency;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\WebhookEventStatus;
use App\Payments\Exceptions\ImmutableRecordException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\GatewayFakes;
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

    Notification::fake();
});

/**
 * A paid Stripe payment, and Stripe reporting a dispute on it in whatever
 * status $stripe['status'] holds.
 *
 * @param  array<string, mixed>  $stripe
 */
function disputedStripePayment(array &$stripe): Payment
{
    $stripe += ['status' => 'needs_response'];

    GatewayFakes::stripe([
        'GET /v1/disputes/dp_test_D1' => function () use (&$stripe) {
            return Http::response(PaymentFixtures::load('stripe/dispute', ['status' => $stripe['status']]));
        },
    ]);

    return Payment::factory()->gateway(Gateway::Stripe)->paid()->create(['gateway_payment_id' => 'pi_test_P4y', 'amount' => 11300]);
}

test('a Stripe dispute is recorded against its payment and operators are told once', function () {
    $stripe = [];
    $payment = disputedStripePayment($stripe);

    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created'))->assertOk();
    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created', ['id' => 'evt_test_Dsp2', 'type' => 'charge.dispute.updated']))->assertOk();

    expect($payment->disputes()->sole())
        ->gateway_dispute_id->toBe('dp_test_D1')
        ->status->toBe(DisputeStatus::NeedsResponse)
        ->amount->toBe(11300)
        ->reason->toBe('fraudulent')
        ->evidence_due_by->getTimestamp()->toBe(1790899200)
        ->and(WebhookEvent::query()->pluck('status')->all())->toBe([WebhookEventStatus::Processed, WebhookEventStatus::Processed]);

    Notification::assertSentOnDemandTimes(DisputeOpened::class, 1);
});

test('a decided dispute is closed, and a stale read cannot reopen it', function () {
    $stripe = [];
    $payment = disputedStripePayment($stripe);
    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created'))->assertOk();

    $stripe['status'] = 'lost';
    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created', ['id' => 'evt_test_Closed', 'type' => 'charge.dispute.closed']))->assertOk();

    $stripe['status'] = 'needs_response';
    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created', ['id' => 'evt_test_Late', 'type' => 'charge.dispute.updated']))->assertOk();

    expect($payment->disputes()->sole())
        ->status->toBe(DisputeStatus::Lost)
        ->closed_at->not->toBeNull()
        ->evidence_due_by->toBeNull();
});

test('an inquiry closed without a chargeback counts as won', function () {
    $stripe = ['status' => 'warning_closed'];
    $payment = disputedStripePayment($stripe);

    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created'))->assertOk();

    expect($payment->disputes()->sole()->status)->toBe(DisputeStatus::Won);

    // Nothing to respond to, so nothing to be alerted about.
    Notification::assertNothingSent();
});

test('a dispute on a payment this site did not make is ignored', function () {
    GatewayFakes::stripe(['GET /v1/disputes/dp_test_D1' => PaymentFixtures::load('stripe/dispute')]);

    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created'))->assertOk();

    expect(Dispute::count())->toBe(0)
        ->and(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Ignored);
});

test('a PayPal dispute is matched to the payment through its capture', function () {
    $payment = Payment::factory()->gateway(Gateway::PayPal)->paid()->create(['amount' => 11300]);
    $capture = $payment->transactions()->sole()->gateway_transaction_id;

    GatewayFakes::paypal([
        'POST /v1/notifications/verify-webhook-signature' => ['verification_status' => 'SUCCESS'],
        'GET /v1/customer/disputes/PP-D-27803' => PaymentFixtures::load('paypal/dispute', ['disputed_transactions' => [['seller_transaction_id' => $capture]]]),
    ]);

    GatewayFakes::deliverPayPal(PaymentFixtures::load('paypal/event_dispute_created'))->assertOk();

    expect($payment->disputes()->sole())
        ->status->toBe(DisputeStatus::NeedsResponse)
        ->amount->toBe(11300)
        ->reason->toBe('MERCHANDISE_OR_SERVICE_NOT_RECEIVED')
        ->evidence_due_by->toIso8601ZuluString()->toBe('2026-10-04T10:00:00Z');
});

test('a PayPal dispute resolved for the buyer is lost, for the seller won', function (string $outcome, DisputeStatus $status) {
    $payment = Payment::factory()->gateway(Gateway::PayPal)->paid()->create();
    $capture = $payment->transactions()->sole()->gateway_transaction_id;

    GatewayFakes::paypal([
        'POST /v1/notifications/verify-webhook-signature' => ['verification_status' => 'SUCCESS'],
        'GET /v1/customer/disputes/PP-D-27803' => PaymentFixtures::load('paypal/dispute', [
            'status' => 'RESOLVED',
            'dispute_outcome' => ['outcome_code' => $outcome],
            'disputed_transactions' => [['seller_transaction_id' => $capture]],
        ]),
    ]);

    GatewayFakes::deliverPayPal(PaymentFixtures::load('paypal/event_dispute_created'))->assertOk();

    expect($payment->disputes()->sole()->status)->toBe($status);
})->with([
    ['RESOLVED_BUYER_FAVOUR', DisputeStatus::Lost],
    ['RESOLVED_SELLER_FAVOUR', DisputeStatus::Won],
    ['CANCELED_BY_BUYER', DisputeStatus::Won],
]);

test('the dispute alert gives the deadline and links to the gateway', function () {
    $dispute = Dispute::factory()->create(['gateway_dispute_id' => 'dp_test_D1']);

    $mail = (new DisputeOpened($dispute))->toMail(new AnonymousNotifiable);

    expect($mail->actionUrl)->toBe('https://dashboard.stripe.com/test/disputes/dp_test_D1')
        ->and(implode("\n", $mail->introLines))->toContain('fraudulent')->toContain('Evidence must be submitted by');
});

test('the dispute screens are for payment viewers, and count disputes needing a response', function () {
    $open = Dispute::factory()->create();
    Dispute::factory()->status(DisputeStatus::Won)->create();
    Dispute::factory()->create(['mode' => GatewayMode::Live]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(DisputeResource::getUrl('view', ['record' => $open]))->assertOk();
    $this->actingAs(User::factory()->create())->get('/admin/disputes')->assertForbidden();

    $this->actingAs($admin);
    Livewire::test(ListDisputes::class)->assertCanSeeTableRecords([$open]);

    expect(DisputeResource::getNavigationBadge())->toBe('1');
});

test('nobody may edit or delete a dispute', function () {
    $dispute = Dispute::factory()->create();
    $admin = User::factory()->admin()->create();

    expect($admin->can('update', $dispute))->toBeFalse()
        ->and($admin->can('delete', $dispute))->toBeFalse()
        ->and(fn () => $dispute->delete())->toThrow(ImmutableRecordException::class)
        ->and(fn () => $dispute->update(['gateway_dispute_id' => 'dp_other']))->toThrow(ImmutableRecordException::class);
});

test('a disputed payment cannot also be refunded', function (DisputeStatus $status) {
    $dispute = Dispute::factory()->status($status)->create();

    app(RefundPayment::class)->handle($dispute->payment, Money::of(1000, Currency::CAD), 'refund:disputed');
})->with([DisputeStatus::NeedsResponse, DisputeStatus::UnderReview, DisputeStatus::Lost])->throws(PaymentNotAllowed::class, 'This payment is disputed');

test('a payment whose dispute was won can be refunded again', function () {
    // A Demo payment, so the refund is made without a gateway to fake.
    $dispute = Dispute::factory()->status(DisputeStatus::Won)->create(['payment_id' => Payment::factory()->paid()->create()->id]);

    expect(app(RefundPayment::class)->handle($dispute->payment, Money::of(1000, Currency::CAD), 'refund:won')->amount)->toBe(1000);
});

test('a dispute already recorded is found again whichever payment reference the gateway gives', function () {
    $stripe = [];
    $payment = disputedStripePayment($stripe);
    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created'))->assertOk();

    // Recorded on another payment than the one the next event resolves to.
    Dispute::query()->sole()->forceFill(['payment_id' => Payment::factory()->gateway(Gateway::Stripe)->paid()->create()->id])->saveQuietly();
    $stripe['status'] = 'under_review';

    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_dispute_created', ['id' => 'evt_test_Again', 'type' => 'charge.dispute.updated']))->assertOk();

    expect(Dispute::sole()->status)->toBe(DisputeStatus::UnderReview)
        ->and($payment->disputes()->count())->toBe(0);
});
