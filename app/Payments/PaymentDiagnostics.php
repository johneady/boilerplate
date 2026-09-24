<?php

namespace App\Payments;

use App\Models\Plan;
use App\Models\WebhookEvent;
use App\Payments\Actions\SyncPlan;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\WebhookEventStatus;
use App\Settings\DiagnosticResult;
use App\Settings\DiagnosticSeverity;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * Checks on the payment configuration, shown on Admin -> Settings -> Diagnostics
 * beside App\Settings\ProductionDiagnostics.
 *
 * Kept apart from ProductionDiagnostics because these read the settings table,
 * and that class is deliberately database-free (its unit test runs with no
 * database at all). Nothing is reported while payments are switched off: a
 * module an installation does not use has nothing to warn about.
 */
class PaymentDiagnostics
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PaymentManager $payments,
        private readonly PaymentCredentials $credentials,
    ) {}

    /**
     * @return list<DiagnosticResult>
     */
    public function run(): array
    {
        $undecryptable = $this->settings->undecryptableKeys();

        // Reported even while payments are off: stored credentials that can no
        // longer be read will fail the day payments are switched back on.
        $results = [
            $this->check(
                'Payment credentials readable',
                $undecryptable === [],
                DiagnosticSeverity::Error,
                'Stored payment credentials cannot be decrypted ('.implode(', ', array_map(fn (SettingKey $key): string => $key->label(), $undecryptable)).'). The application key was probably changed without listing the old one in APP_PREVIOUS_KEYS. Restore it, or enter the credentials again.',
                'Every stored payment credential can be decrypted.',
            ),
        ];

        if (! $this->payments->enabled()) {
            return $results;
        }

        $mode = $this->payments->mode();

        $results[] = $this->check(
            'Payment gateways',
            $this->payments->checkoutGateways() !== [],
            DiagnosticSeverity::Error,
            "Payments are switched on, but no gateway is both enabled and configured for {$mode->label()} mode, so customers have no way to pay.",
            'At least one gateway is offered at checkout.',
        );

        foreach ([Gateway::Stripe, Gateway::PayPal] as $gateway) {
            if (! $this->isSwitchedOn($gateway)) {
                continue;
            }

            $results[] = $this->check(
                "{$gateway->label()} credentials",
                $this->credentials->isConfigured($gateway, $mode),
                DiagnosticSeverity::Error,
                "{$gateway->label()} is enabled but has no {$mode->label()} credentials, so it is not offered at checkout.",
                "{$gateway->label()} has {$mode->label()} credentials.",
            );

            $results[] = $this->check(
                "{$gateway->label()} webhooks",
                $this->credentials->hasWebhookSecret($gateway, $mode),
                DiagnosticSeverity::Warning,
                "No {$gateway->label()} webhook secret is set for {$mode->label()} mode. Payments still complete when the customer returns, but refunds made in the {$gateway->label()} dashboard, disputes and customers who close the tab early are only picked up by the scheduled reconciliation.",
                "{$gateway->label()} webhooks can be verified.",
            );
        }

        $failedWebhooks = WebhookEvent::query()
            ->where('status', WebhookEventStatus::Failed->value)
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $results[] = $this->check(
            'Webhook processing',
            $failedWebhooks === 0,
            DiagnosticSeverity::Warning,
            "{$failedWebhooks} webhook ".str('event')->plural($failedWebhooks).' failed in the last 24 hours after every retry, so what '.($failedWebhooks === 1 ? 'it' : 'they').' reported may be missing. Retry '.($failedWebhooks === 1 ? 'it' : 'them').' from Payments -> Webhook events once the cause is fixed.',
            'No webhook event has failed in the last 24 hours.',
        );

        $plans = Plan::query()->where('is_active', true)->whereHas('prices', fn ($prices) => $prices->where('is_active', true))->get();

        if ($plans->isNotEmpty()) {
            $unsynced = [];

            foreach ($plans as $plan) {
                foreach (app(SyncPlan::class)->status($plan) as $gateway => $synced) {
                    if (! $synced) {
                        $unsynced[] = "{$plan->name} ({$gateway})";
                    }
                }
            }

            $results[] = $this->check(
                'Subscription plans synced',
                $unsynced === [],
                DiagnosticSeverity::Warning,
                'These plans are not up to date at the gateway, so customers cannot subscribe through it or would get an old trial or tax: '.implode(', ', $unsynced).'. Open each plan and use Sync to see why.',
                'Every active plan is up to date at the gateways.',
            );
        }

        if ($mode === GatewayMode::Live && $this->isSwitchedOn(Gateway::Stripe)) {
            $results[] = $this->check(
                'Stripe live key',
                ! str_starts_with($this->credentials->stripe(GatewayMode::Live)['secret_key'], 'sk_test_'),
                DiagnosticSeverity::Error,
                'The Stripe key saved as the live key is a test key (sk_test_...), so no real payment can be taken.',
                'The Stripe live key is a live key.',
            );
        }

        if (config('app.env') === 'production') {
            $results[] = $this->check(
                'Payment mode',
                $mode === GatewayMode::Live,
                DiagnosticSeverity::Warning,
                'Payments are in sandbox mode on a production site: customers can check out, but no real money moves.',
                'Payments are in live mode.',
            );
        }

        return $results;
    }

    /**
     * @return list<DiagnosticResult>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->run(), fn (DiagnosticResult $result): bool => ! $result->passed));
    }

    private function isSwitchedOn(Gateway $gateway): bool
    {
        return match ($gateway) {
            Gateway::Stripe => $this->settings->boolean(SettingKey::StripeEnabled),
            Gateway::PayPal => $this->settings->boolean(SettingKey::PayPalEnabled),
            Gateway::Demo, Gateway::Manual => false,
        };
    }

    private function check(string $name, bool $passed, DiagnosticSeverity $severity, string $failureDetail, string $passedDetail): DiagnosticResult
    {
        return new DiagnosticResult(
            name: $name,
            passed: $passed,
            severity: $passed ? DiagnosticSeverity::Passed : $severity,
            detail: $passed ? $passedDetail : $failureDetail,
        );
    }
}
