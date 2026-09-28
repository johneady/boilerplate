<?php

namespace App\Models;

use App\Prints\Qr\LocationQrCode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical counter whose QR code sends customers into the in-store flow.
 *
 * One row per place a customer can stand and scan: the front counter, the
 * kiosk at the marina gate. The slug is the URL segment the QR code encodes,
 * so printing new signage for a new counter is adding a row.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $address
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PrintLocation extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * The QR code a staff member prints and tapes beside the counter: an SVG
     * encoding this location's order URL. SVG rather than PNG so it scales to
     * any sign without a rasteriser on the server.
     */
    public function qrCodeSvg(): string
    {
        return LocationQrCode::forLocation($this);
    }

    /**
     * @return HasMany<PrintOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(PrintOrder::class);
    }

    /**
     * Limit the query to locations whose QR codes answer.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
