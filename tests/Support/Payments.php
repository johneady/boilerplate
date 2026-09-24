<?php

namespace Tests\Support;

use App\Models\Payment;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Actions\StartCheckout;
use App\Payments\Contracts\Payable;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\TransactionSource;
use App\Payments\Money;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Shared arrangement for the payments tests.
 *
 * Money movements are arranged through the real actions and the Demo gateway
 * rather than by writing rows, so a test of a refund starts from a payment
 * that really went through checkout, the ledger and the payable.
 */
class Payments
{
    /**
     * Switch payments on, with the Demo gateway and manual payments offered.
     *
     * @param  array<string, mixed>  $overrides  Keyed by SettingKey value.
     */
    public static function enable(array $overrides = []): void
    {
        app(Settings::class)->setMany([
            SettingKey::PaymentsEnabled->value => true,
            SettingKey::PaymentsMode->value => 'sandbox',
            SettingKey::PaymentsCurrency->value => 'CAD',
            SettingKey::DemoGatewayEnabled->value => true,
            SettingKey::ManualPaymentsEnabled->value => true,
            ...$overrides,
        ]);
    }

    /**
     * Start a checkout on a payable, as the pay page would.
     */
    public static function checkout(Payable&Model $payable, Gateway $gateway = Gateway::Demo, ?Money $offered = null, ?string $key = null): Payment
    {
        return app(StartCheckout::class)->handle($payable, new CheckoutRequest(
            gateway: $gateway,
            customerName: 'Sam Customer',
            customerEmail: 'sam@example.test',
            idempotencyKey: $key ?? 'test:'.Str::uuid(),
            offeredAmount: $offered,
        ));
    }

    /**
     * Take a payable through the Demo gateway: checkout, the customer's choice
     * on the demo page, and the return that reconciles it.
     */
    public static function payWithDemo(Payable&Model $payable, string $outcome = 'approve'): Payment
    {
        $payment = static::checkout($payable);

        app(DemoDriver::class)->simulateCustomer($payment, $outcome);

        return app(ReconcilePayment::class)->handle($payment->refresh(), TransactionSource::Return);
    }
}
