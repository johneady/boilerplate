<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One photo in a print order, and how many copies of it were asked for.
 *
 * The photo itself is a media row attached to the order (the OrderPhoto
 * collection); this row carries the PRINT request -- the quantity, and which
 * printer it was last sent to -- which is what the fulfillment console works
 * through.
 *
 * @property int $id
 * @property int $print_order_id
 * @property int $media_id
 * @property int $quantity
 * @property string|null $printer
 * @property CarbonImmutable|null $printed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PrintOrderItem extends Model
{
    /**
     * Written with its order by the wizard, never mass-assigned.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<PrintOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'print_order_id');
    }

    /**
     * The photo this line prints.
     *
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
