<?php

namespace App\Payments;

use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * The gateway credentials for a mode, read from the encrypted settings.
 *
 * Resolved per call through the scoped Settings instance, so a queue worker
 * picks up credentials an administrator changed since it booted.
 */
class PaymentCredentials
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{secret_key: string, webhook_secret: string}
     */
    public function stripe(GatewayMode $mode): array
    {
        return match ($mode) {
            GatewayMode::Sandbox => [
                'secret_key' => $this->settings->string(SettingKey::StripeSandboxSecretKey),
                'webhook_secret' => $this->settings->string(SettingKey::StripeSandboxWebhookSecret),
            ],
            GatewayMode::Live => [
                'secret_key' => $this->settings->string(SettingKey::StripeLiveSecretKey),
                'webhook_secret' => $this->settings->string(SettingKey::StripeLiveWebhookSecret),
            ],
        };
    }

    /**
     * @return array{client_id: string, client_secret: string, webhook_id: string}
     */
    public function paypal(GatewayMode $mode): array
    {
        return match ($mode) {
            GatewayMode::Sandbox => [
                'client_id' => $this->settings->string(SettingKey::PayPalSandboxClientId),
                'client_secret' => $this->settings->string(SettingKey::PayPalSandboxClientSecret),
                'webhook_id' => $this->settings->string(SettingKey::PayPalSandboxWebhookId),
            ],
            GatewayMode::Live => [
                'client_id' => $this->settings->string(SettingKey::PayPalLiveClientId),
                'client_secret' => $this->settings->string(SettingKey::PayPalLiveClientSecret),
                'webhook_id' => $this->settings->string(SettingKey::PayPalLiveWebhookId),
            ],
        };
    }

    /**
     * Whether a gateway has what it needs to take a payment in this mode.
     *
     * Webhook secrets are not required to take a payment -- the customer's
     * return reconciles it -- so they are reported by the diagnostics rather
     * than blocking checkout.
     */
    public function isConfigured(Gateway $gateway, GatewayMode $mode): bool
    {
        return match ($gateway) {
            Gateway::Stripe => $this->stripe($mode)['secret_key'] !== '',
            Gateway::PayPal => $this->paypal($mode)['client_id'] !== '' && $this->paypal($mode)['client_secret'] !== '',
            Gateway::Demo, Gateway::Manual => true,
        };
    }

    /**
     * Whether the gateway can verify webhooks in this mode.
     */
    public function hasWebhookSecret(Gateway $gateway, GatewayMode $mode): bool
    {
        return match ($gateway) {
            Gateway::Stripe => $this->stripe($mode)['webhook_secret'] !== '',
            Gateway::PayPal => $this->paypal($mode)['webhook_id'] !== '',
            Gateway::Demo, Gateway::Manual => false,
        };
    }
}
