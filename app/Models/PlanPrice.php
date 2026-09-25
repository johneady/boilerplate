<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\GuardsFinancialRecord;
use App\Jobs\SyncPlans;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * What a plan costs, and how often: $29.00 CAD a month.
 *
 * Never edited once created (App\Concerns\GuardsFinancialRecord): a new price
 * is a new row and a new price at each gateway, and existing subscribers stay
 * on the one they signed up for. A price is retired by deactivating it, which
 * archives it at the gateways; it is never deleted, because subscriptions and
 * their payments name it.
 *
 * gateway_refs holds, per gateway and mode, the gateway's id for this price
 * (a Stripe Price, a PayPal billing plan) and every id it has had, so a
 * subscription still billed on a superseded PayPal plan is still recognised.
 * Where the gateway fixes the trial on the price itself (PayPal), no_trial
 * holds the same for a variant without the plan's trial, which customers who
 * have had one subscribe on.
 *
 * @property int $id
 * @property int $plan_id
 * @property Currency $currency
 * @property int $amount
 * @property BillingInterval $interval
 * @property int $interval_count
 * @property bool $is_active
 * @property array<string, array<string, array{id: string, history?: list<string>, signature?: string, tax_name?: string, tax_percentage?: string, no_trial?: array{id: string, history?: list<string>, signature?: string, tax_name?: string, tax_percentage?: string}}>>|null $gateway_refs
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Plan|null $plan
 */
#[Fillable(['plan_id', 'currency', 'amount', 'interval', 'interval_count', 'is_active'])]
class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use Auditable, GuardsFinancialRecord, HasFactory;

    protected static function booted(): void
    {
        // See Plan::booted() for why these are not one saved hook.
        static::created(fn () => SyncPlans::dispatchForCurrentMode());

        static::updated(function (PlanPrice $price): void {
            if ($price->wasChanged('is_active')) {
                SyncPlans::dispatchForCurrentMode();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => 'integer',
            'interval' => BillingInterval::class,
            'interval_count' => 'integer',
            'is_active' => 'boolean',
            'gateway_refs' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    protected function writeOnceAttributes(): array
    {
        return ['plan_id', 'currency', 'amount', 'interval', 'interval_count'];
    }

    /**
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['gateway_refs', 'updated_at'];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    /**
     * "month", "3 months", "year".
     */
    public function intervalDescription(): string
    {
        return $this->interval->describe($this->interval_count);
    }

    /**
     * "$29.00 / month", as shown beside a plan.
     */
    public function label(): string
    {
        return __(':amount / :interval', ['amount' => $this->money()->format(), 'interval' => __($this->intervalDescription())]);
    }

    /**
     * What this price is known as at a gateway, in a mode, once synced.
     *
     * @return array{id: string, history?: list<string>, signature?: string, tax_name?: string, tax_percentage?: string, no_trial?: array{id: string, history?: list<string>, signature?: string, tax_name?: string, tax_percentage?: string}}|null
     */
    public function gatewayRef(Gateway $gateway, GatewayMode $mode): ?array
    {
        return $this->gateway_refs[$gateway->value][$mode->value] ?? null;
    }

    /**
     * Record the gateway's current id for this price, keeping every id it
     * has had before.
     *
     * @param  array{id: string, signature?: string, tax_name?: string, tax_percentage?: string}  $ref
     */
    public function recordGatewayRef(Gateway $gateway, GatewayMode $mode, array $ref): void
    {
        $previous = $this->gatewayRef($gateway, $mode);

        $refs = $this->gateway_refs ?? [];
        $refs[$gateway->value][$mode->value] = [
            ...$ref,
            'history' => self::historyAfter($previous, $ref['id']),
            ...(isset($previous['no_trial']) ? ['no_trial' => $previous['no_trial']] : []),
        ];
        $this->gateway_refs = $refs;
        $this->save();
    }

    /**
     * Record the gateway's current id for this price's no-trial variant,
     * keeping every id the variant has had before.
     *
     * @param  array{id: string, signature?: string, tax_name?: string, tax_percentage?: string}  $ref
     */
    public function recordNoTrialGatewayRef(Gateway $gateway, GatewayMode $mode, array $ref): void
    {
        $current = $this->gatewayRef($gateway, $mode)
            ?? throw new LogicException('A price\'s own gateway id is recorded before its no-trial variant\'s.');

        $current['no_trial'] = [...$ref, 'history' => self::historyAfter($current['no_trial'] ?? null, $ref['id'])];

        $refs = $this->gateway_refs ?? [];
        $refs[$gateway->value][$mode->value] = $current;
        $this->gateway_refs = $refs;
        $this->save();
    }

    /**
     * The ids a ref has had, including the one it is being moved off.
     *
     * @param  array{id: string, history?: list<string>}|null  $previous
     * @return list<string>
     */
    private static function historyAfter(?array $previous, string $newId): array
    {
        $history = $previous['history'] ?? [];

        if ($previous !== null && ! in_array($previous['id'], $history, true)) {
            $history[] = $previous['id'];
        }

        return array_values(array_diff($history, [$newId]));
    }

    /**
     * The price a gateway id belongs to, current or superseded, the price's
     * own or its no-trial variant's.
     */
    public static function findByGatewayRef(Gateway $gateway, GatewayMode $mode, string $gatewayId): ?self
    {
        // Few enough rows (a handful of plans, a few prices each) that
        // scanning beats a JSON query that reads differently on every database.
        foreach (self::query()->whereNotNull('gateway_refs')->get() as $price) {
            $ref = $price->gatewayRef($gateway, $mode);

            foreach ([$ref, $ref['no_trial'] ?? null] as $known) {
                if ($known !== null && ($known['id'] === $gatewayId || in_array($gatewayId, $known['history'] ?? [], true))) {
                    return $price;
                }
            }
        }

        return null;
    }
}
