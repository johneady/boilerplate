<?php

namespace Database\Factories;

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A subscription row made directly, for access checks and screens.
 *
 * Tests of how a subscription moves should go through the real actions
 * (StartSubscription, the Demo gateway, ReconcileSubscription) instead.
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * An active Demo subscription in sandbox mode, mid-period.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => 'factory:'.Str::uuid(),
            'status' => SubscriptionStatus::Active,
            'user_id' => User::factory(),
            // Held while live, like the real thing, so the one-live-subscription
            // rule applies to factory rows too.
            'active_user_id' => fn (array $attributes): mixed => SubscriptionStatus::from($attributes['status'] instanceof SubscriptionStatus ? $attributes['status']->value : (string) $attributes['status'])->isLive()
                ? $attributes['user_id']
                : null,
            'plan_price_id' => PlanPrice::factory(),
            'plan_id' => fn (array $attributes): int => PlanPrice::query()->whereKey($attributes['plan_price_id'])->firstOrFail()->plan_id,
            'gateway' => Gateway::Demo,
            'mode' => GatewayMode::Sandbox,
            'currency' => Currency::CAD,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Subscription $subscription): void {
            $subscription->current_period_start ??= now()->subDays(10)->toImmutable();
            $subscription->current_period_end ??= now()->addDays(20)->toImmutable();
        });
    }

    public function status(SubscriptionStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }

    public function forPrice(PlanPrice $price): static
    {
        return $this->state(fn (array $attributes): array => ['plan_price_id' => $price->id, 'plan_id' => $price->plan_id]);
    }
}
