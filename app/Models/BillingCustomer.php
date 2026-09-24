<?php

namespace App\Models;

use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's customer record at a gateway, in one mode (a Stripe Customer).
 *
 * A table rather than columns on users: a user can be a customer of more than
 * one gateway, in both modes, and the users table stays free of payment ids.
 *
 * @property int $id
 * @property int $user_id
 * @property Gateway $gateway
 * @property GatewayMode $mode
 * @property string $gateway_customer_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'gateway', 'mode', 'gateway_customer_id'])]
class BillingCustomer extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => Gateway::class,
            'mode' => GatewayMode::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
