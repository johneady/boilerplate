<?php

use App\Models\PaymentLink;
use App\Models\Refund;
use App\Models\WebhookEvent;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use Illuminate\Support\Facades\URL;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('a checkout abandoned past the cutoff is expired', function () {
    $payment = Payments::checkout(PaymentLink::factory()->create());

    $this->travel(25)->hours();
    $this->artisan('payments:expire-checkouts')->assertSuccessful();

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Expired)
        ->expired_at->not->toBeNull();
});

test('a recent checkout is left alone', function () {
    $payment = Payments::checkout(PaymentLink::factory()->create());

    $this->travel(2)->hours();
    $this->artisan('payments:expire-checkouts')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('a customer who paid just before the cutoff is recorded as paid, not expired', function () {
    $payment = Payments::checkout(PaymentLink::factory()->create());
    // Paid at the gateway, but neither the return nor a webhook recorded it.
    app(DemoDriver::class)->simulateCustomer($payment, 'approve');

    $this->travel(25)->hours();
    $this->artisan('payments:expire-checkouts')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('an expired checkout can no longer be paid through', function () {
    $payment = Payments::checkout(PaymentLink::factory()->create());

    $this->travel(25)->hours();
    $this->artisan('payments:expire-checkouts')->assertSuccessful();

    $this->get(URL::signedRoute('payments.demo.show', $payment))->assertNotFound();
});

test('a payment whose webhooks never came is picked up by the stale reconciliation', function () {
    $payment = Payments::checkout(PaymentLink::factory()->create());
    app(DemoDriver::class)->simulateCustomer($payment, 'approve');

    $this->travel(20)->minutes();
    $this->artisan('payments:reconcile-stale')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('refunds slow to settle cannot crowd out payments waiting to be recorded', function () {
    config()->set('payments.reconcile_batch_size', 1);

    $refunded = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    Refund::factory()->for($refunded)->create(['gateway_refund_id' => 'demo_re_slow', 'status' => RefundStatus::Pending]);

    $unrecorded = Payments::checkout(PaymentLink::factory()->create());
    app(DemoDriver::class)->simulateCustomer($unrecorded, 'approve');

    $this->travel(20)->minutes();
    $this->artisan('payments:reconcile-stale')->assertSuccessful();

    expect($unrecorded->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('webhook events are pruned after their retention, and only then', function () {
    $old = WebhookEvent::factory()->create(['created_at' => now()->subDays(91)]);
    $recent = WebhookEvent::factory()->create(['created_at' => now()->subDays(89)]);

    $this->artisan('payments:prune-webhook-events')->assertSuccessful();

    expect(WebhookEvent::query()->pluck('id')->all())->toBe([$recent->id]);
});
