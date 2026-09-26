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
     * The plan a price belongs to, memoised per factory instance so a
     * count()-style run that reuses one price reads it once.
     *
     * @var array<int, int>
     */
    private array $planByPriceId = [];

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
            // Resolved through the per-instance memo below: a run that
            // reuses one price (count(), forPrice()) pays one read instead
            // of one per subscription. forPrice() sets this attribute
            // directly, so the closure only runs for the factory's own
            // prices.
            'plan_id' => fn (array $attributes): int => $this->planForPrice($attributes['plan_price_id']),
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

    /**
     * The plan that owns a price, read once per price per factory instance.
     *
     * Deliberately per-instance and not static, so a fresh test database can
     * never be answered from a stale mapping.
     */
    private function planForPrice(mixed $priceId): int
    {
        $priceId = (int) $priceId;

        return $this->planByPriceId[$priceId] ??= PlanPrice::query()
            ->whereKey($priceId)
            ->firstOrFail()
            ->plan_id;
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
