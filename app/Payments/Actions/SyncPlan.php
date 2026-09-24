<?php

namespace App\Payments\Actions;

use App\Models\Plan;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Exceptions\GatewayException;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\Cache;

/**
 * Copy a plan and its prices out to a gateway.
 *
 * Plans are defined in the admin panel and synced out, never the other way
 * round. Each gateway object is created with an idempotency key derived from
 * the plan or price, and the whole sync for one plan, gateway and mode runs
 * under a lock, so a sync triggered twice at once (a save and the Sync
 * button) creates each product and price once.
 */
class SyncPlan
{
    public function __construct(private readonly PaymentManager $payments) {}

    public function handle(Plan $plan, Gateway $gateway, GatewayMode $mode): void
    {
        Cache::lock("payments:sync-plan:{$plan->id}:{$gateway->value}:{$mode->value}", 120)
            ->block(60, fn () => $this->payments->subscriptionDriver($gateway, $mode)->syncPlan($plan->fresh() ?? $plan));
    }

    /**
     * Sync only if something is missing or out of date.
     */
    public function ensure(Plan $plan, Gateway $gateway, GatewayMode $mode): void
    {
        if (! $this->payments->subscriptionDriver($gateway, $mode)->isSynced($plan)) {
            $this->handle($plan, $gateway, $mode);
        }
    }

    /**
     * Every plan to every gateway customers can subscribe through, in the
     * current mode.
     *
     * One plan or gateway failing does not stop the rest; the failures are
     * returned, keyed "Plan name (Gateway)".
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $failures = [];
        $mode = $this->payments->mode();

        foreach (Plan::query()->orderBy('id')->get() as $plan) {
            foreach ($this->payments->subscriptionGateways() as $gateway) {
                try {
                    $this->handle($plan, $gateway, $mode);
                } catch (GatewayException $e) {
                    report($e);
                    $failures["{$plan->name} ({$gateway->label()})"] = $e->getMessage();
                }
            }
        }

        return $failures;
    }

    /**
     * Whether a plan is up to date on every gateway customers can subscribe
     * through, in the current mode. For the admin panel and diagnostics.
     *
     * @return array<string, bool> Keyed by gateway label.
     */
    public function status(Plan $plan): array
    {
        $status = [];

        foreach ($this->payments->subscriptionGateways() as $gateway) {
            $status[$gateway->label()] = $this->payments->subscriptionDriver($gateway, $this->payments->mode())->isSynced($plan);
        }

        return $status;
    }
}
