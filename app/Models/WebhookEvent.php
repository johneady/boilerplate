<?php

namespace App\Models;

use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\WebhookEventStatus;
use Carbon\CarbonImmutable;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A verified webhook delivery from Stripe or PayPal.
 *
 * Stored before it is processed, unique on (gateway, event_id), so a
 * redelivered event is acknowledged without being processed twice. The
 * payload is never trusted for state: processing re-reads the payment from
 * the gateway's API (see App\Payments\Actions\ReconcilePayment).
 *
 * Not audited: the payload carries the customer's name and email, and this
 * table has its own retention (config('payments.webhook_retention_days')).
 *
 * @property int $id
 * @property Gateway $gateway
 * @property GatewayMode $mode
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property WebhookEventStatus $status
 * @property int $attempts
 * @property CarbonImmutable|null $processed_at
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['gateway', 'mode', 'event_id', 'type', 'payload', 'status', 'attempts', 'processed_at', 'error'])]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

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
            'status' => WebhookEventStatus::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
