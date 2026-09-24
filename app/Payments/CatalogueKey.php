<?php

namespace App\Payments;

use App\Payments\Enums\GatewayMode;
use Illuminate\Database\Eloquent\Model;

/**
 * The idempotency key for creating a gateway catalogue object (a product, a
 * price, a tax rate, a customer) from one of our records.
 *
 * Carries the record's creation time as well as its id. Ids alone repeat
 * across installations: a staging copy sharing the production sandbox
 * account, or a database rebuilt from scratch, has its own plan 1 -- and
 * within the gateway's 24-hour idempotency window "plan:1" would hand it the
 * other installation's product instead of creating its own.
 */
class CatalogueKey
{
    public static function for(string $kind, Model $record, GatewayMode $mode, string $suffix = ''): string
    {
        $created = $record->getAttribute('created_at');
        $stamp = $created instanceof \DateTimeInterface ? $created->getTimestamp() : 0;

        return "{$kind}:{$record->getKey()}.{$stamp}:{$mode->value}".($suffix !== '' ? ":{$suffix}" : '');
    }
}
