<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Jobs\SyncPlans;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use Carbon\CarbonImmutable;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something a customer subscribes to, e.g. "Pro".
 *
 * Defined here and synced out to the gateways (App\Jobs\SyncPlans), rather
 * than defined in each gateway's dashboard and mirrored: one place to change
 * a plan, and the same plan on every gateway. What it costs lives on its
 * prices (App\Models\PlanPrice), which never change once created.
 *
 * Code checks access by the plan's key: $user->subscribed('pro'), or the
 * subscribed:pro route middleware.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property list<string> $features
 * @property int $trial_days
 * @property bool $taxable
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, array<string, string>>|null $gateway_refs
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['key', 'name', 'description', 'features', 'trial_days', 'taxable', 'is_active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use Auditable, HasFactory;

    /**
     * The attributes the gateways hold a copy of. Changing one re-syncs.
     */
    private const array SYNCED_ATTRIBUTES = ['name', 'description', 'trial_days', 'taxable', 'is_active'];

    protected static function booted(): void
    {
        // created/updated rather than saved: wasRecentlyCreated stays true
        // for the instance's whole life, so a later save of the same
        // instance (recording a gateway id) would re-sync.
        static::created(fn () => SyncPlans::dispatchForCurrentMode());

        static::updated(function (Plan $plan): void {
            if ($plan->wasChanged(self::SYNCED_ATTRIBUTES)) {
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
            'features' => 'array',
            'trial_days' => 'integer',
            'taxable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'gateway_refs' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['gateway_refs', 'updated_at'];
    }

    /**
     * @return HasMany<PlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Plans on offer, in the order they are listed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The gateway's product for this plan, in a mode, once synced.
     */
    public function gatewayRef(Gateway $gateway, GatewayMode $mode): ?string
    {
        return $this->gateway_refs[$gateway->value][$mode->value] ?? null;
    }

    public function recordGatewayRef(Gateway $gateway, GatewayMode $mode, string $ref): void
    {
        $refs = $this->gateway_refs ?? [];
        $refs[$gateway->value][$mode->value] = $ref;
        $this->gateway_refs = $refs;
        $this->save();
    }
}
