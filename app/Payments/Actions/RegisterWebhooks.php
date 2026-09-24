<?php

namespace App\Payments\Actions;

use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * Create this site's webhook endpoint at a gateway and store what verifying
 * it needs, so nobody has to copy a signing secret between dashboards.
 *
 * Re-running it replaces the endpoint (Stripe) or updates it in place
 * (PayPal), so it also brings an existing endpoint's event list up to date
 * after an upgrade adds events.
 */
class RegisterWebhooks
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly Settings $settings,
    ) {}

    /**
     * Returns the setting that was written.
     *
     * @throws PaymentNotAllowed when this site cannot receive webhooks
     * @throws GatewayException when the gateway refuses
     */
    public function handle(Gateway $gateway, GatewayMode $mode): SettingKey
    {
        // From APP_URL, not the request: the admin may be on another host
        // (www or not, a preview domain) or behind an untrusted proxy, and the
        // gateway must be given the address the site is published at.
        $url = rtrim((string) config('app.url'), '/').route('payments.webhook', ['gateway' => $gateway->value, 'mode' => $mode->value], absolute: false);
        $host = (string) parse_url($url, PHP_URL_HOST);

        // Both gateways deliver only to a public HTTPS address; asking them
        // to register anything else fails with a less helpful message.
        if (! str_starts_with($url, 'https://') || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test')) {
            throw new PaymentNotAllowed(__('Webhooks can only be connected from a public HTTPS address (this site is :url). Set APP_URL, or forward webhooks with the Stripe CLI while developing.', ['url' => $url]));
        }

        if (! $gateway->sendsWebhooks()) {
            throw new PaymentNotAllowed(__('That gateway does not send webhooks.'));
        }

        $key = match ([$gateway, $mode]) {
            [Gateway::Stripe, GatewayMode::Sandbox] => SettingKey::StripeSandboxWebhookSecret,
            [Gateway::Stripe, GatewayMode::Live] => SettingKey::StripeLiveWebhookSecret,
            [Gateway::PayPal, GatewayMode::Sandbox] => SettingKey::PayPalSandboxWebhookId,
            default => SettingKey::PayPalLiveWebhookId,
        };

        $this->payments->webhookRegistrar($gateway, $mode)->registerWebhook(
            $url,
            fn (string $verification) => $this->settings->set($key, $verification),
        );

        return $key;
    }
}
