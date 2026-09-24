<?php

use App\Models\Payment;
use App\Notifications\Payments\AuthorizationExpiring;
use App\Payments\Actions\CapturePayment;
use App\Payments\Actions\VoidPayment;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\Payments\HeldBooking;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
    HeldBooking::createTable();
});

function heldPayment(int $price = 10000): Payment
{
    return Payments::payWithDemo(HeldBooking::query()->create(['price' => $price]));
}

test('a payable that asks for a hold is authorized at checkout, not charged', function () {
    $payment = heldPayment();

    expect($payment->status)->toBe(PaymentStatus::Authorized)
        ->and($payment->amount_captured)->toBe(0)
        ->and($payment->authorization_expires_at)->not->toBeNull()
        ->and($payment->paid_at)->toBeNull();
});

test('capturing part of a hold takes that much and releases the rest', function () {
    $payment = heldPayment(10000);

    // HST is not configured, so the hold is exactly the price.
    $captured = app(CapturePayment::class)->handle($payment, Money::of(6000, $payment->currency));

    expect($captured->status)->toBe(PaymentStatus::Succeeded)
        ->and($captured->amount_captured)->toBe(6000)
        ->and($captured->paid_at)->not->toBeNull();
});

test('capturing more than was held is refused', function () {
    $payment = heldPayment(10000);

    app(CapturePayment::class)->handle($payment, Money::of(10001, $payment->currency));
})->throws(PaymentNotAllowed::class);

test('a hold can only be captured once', function () {
    $payment = heldPayment();
    app(CapturePayment::class)->handle($payment, $payment->total());

    app(CapturePayment::class)->handle($payment, $payment->total());
})->throws(PaymentNotAllowed::class);

test('voiding a hold releases it without taking anything', function () {
    $payment = app(VoidPayment::class)->handle(heldPayment());

    expect($payment->status)->toBe(PaymentStatus::Voided)
        ->and($payment->amount_captured)->toBe(0)
        ->and($payment->transactions()->count())->toBe(0);
});

test('operators are warned once about a hold nearing expiry', function () {
    Notification::fake();
    app(Settings::class)->set(SettingKey::OpsAlertEmail, 'ops@example.test');
    $payment = heldPayment();

    $this->travelTo($payment->authorization_expires_at->subHours(10));
    $this->artisan('payments:check-authorizations')->assertSuccessful();
    $this->artisan('payments:check-authorizations')->assertSuccessful();

    Notification::assertSentOnDemandTimes(AuthorizationExpiring::class, 1);
});

test('a hold past its expiry is recorded as expired', function () {
    $payment = heldPayment();

    $this->travelTo($payment->authorization_expires_at->addMinute());
    $this->artisan('payments:check-authorizations')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired);
});
