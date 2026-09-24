<?php

namespace App\Payments;

use App\Auth\DevLoginAccounts;
use App\Models\Payment;
use App\Payments\Contracts\PaymentDriver;
use App\Payments\Contracts\WebhookDriver;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Drivers\ManualDriver;
use App\Payments\Drivers\PayPal\PayPalClient;
use App\Payments\Drivers\PayPal\PayPalDriver;
use App\Payments\Drivers\Stripe\StripeDriver;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Settings\SettingKey;
use App\Settings\Settings;
use InvalidArgumentException;

/**
 * The payments module's switchboard: whether it is on, in which mode and
 * currency, which gateways a customer may choose, and the driver for each.
 *
 * Drivers are resolved per (gateway, mode) rather than for "the current mode",
 * because a payment is always operated on with the credentials it was made
 * with: refunding a sandbox payment after the switch to live must use the
 * sandbox keys.
 */
class PaymentManager
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PaymentCredentials $credentials,
        private readonly DevLoginAccounts $devLogin,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->boolean(SettingKey::PaymentsEnabled);
    }

    public function mode(): GatewayMode
    {
        return GatewayMode::from($this->settings->string(SettingKey::PaymentsMode));
    }

    public function currency(): Currency
    {
        return Currency::from($this->settings->string(SettingKey::PaymentsCurrency));
    }

    /**
     * Whether the Demo gateway may run at all in this environment.
     *
     * The same gate as the passwordless dev login -- every environment except
     * production -- because an instance that is not production already lets
     * anyone sign in as the seeded administrator, so a gateway that takes no
     * money adds nothing on top. In production the setting is ignored and
     * the demo checkout routes are not registered.
     */
    public function demoAllowed(): bool
    {
        return $this->devLogin->enabled();
    }

    public function manualPaymentsEnabled(): bool
    {
        return $this->settings->boolean(SettingKey::ManualPaymentsEnabled);
    }

    /**
     * Whether a customer may choose this gateway at checkout right now.
     */
    public function offers(Gateway $gateway): bool
    {
        $switchedOn = match ($gateway) {
            Gateway::Stripe => $this->settings->boolean(SettingKey::StripeEnabled),
            Gateway::PayPal => $this->settings->boolean(SettingKey::PayPalEnabled),
            Gateway::Demo => $this->settings->boolean(SettingKey::DemoGatewayEnabled) && $this->demoAllowed(),
            Gateway::Manual => false,
        };

        return $switchedOn && $this->credentials->isConfigured($gateway, $this->mode());
    }

    /**
     * The gateways a customer may choose between, in display order.
     *
     * @return list<Gateway>
     */
    public function checkoutGateways(): array
    {
        return array_values(array_filter(Gateway::cases(), fn (Gateway $gateway): bool => $this->offers($gateway)));
    }

    /**
     * The driver for a gateway, bound to one mode's credentials.
     */
    public function driver(Gateway $gateway, GatewayMode $mode): PaymentDriver
    {
        return match ($gateway) {
            Gateway::Stripe => new StripeDriver($mode, $this->credentials->stripe($mode)),
            Gateway::PayPal => new PayPalDriver($this->paypalClient($mode)),
            Gateway::Demo => $this->demoDriver(),
            Gateway::Manual => new ManualDriver,
        };
    }

    /**
     * The driver a payment must be operated on with: its own gateway, in the
     * mode it was made in.
     */
    public function driverFor(Payment $payment): PaymentDriver
    {
        return $this->driver($payment->gateway, $payment->mode);
    }

    public function webhookDriver(Gateway $gateway, GatewayMode $mode): WebhookDriver
    {
        return match ($gateway) {
            Gateway::Stripe => new StripeDriver($mode, $this->credentials->stripe($mode)),
            Gateway::PayPal => new PayPalDriver($this->paypalClient($mode)),
            Gateway::Demo, Gateway::Manual => throw new InvalidArgumentException("{$gateway->label()} does not send webhooks."),
        };
    }

    private function demoDriver(): DemoDriver
    {
        // Refused here as well as at the routes, so no code path -- a queued
        // job, a console command -- can settle a payment through the Demo
        // gateway in production.
        if (! $this->demoAllowed()) {
            throw new PaymentNotAllowed('The demo gateway is not available in this environment.');
        }

        return new DemoDriver;
    }

    private function paypalClient(GatewayMode $mode): PayPalClient
    {
        $credentials = $this->credentials->paypal($mode);

        return new PayPalClient(
            baseUrl: (string) config("payments.paypal.base_urls.{$mode->value}"),
            clientId: $credentials['client_id'],
            clientSecret: $credentials['client_secret'],
            webhookId: $credentials['webhook_id'],
        );
    }
}
