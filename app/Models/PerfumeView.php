<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One perfume's page views on one day.
 *
 * @property int $perfume_id
 * @property CarbonImmutable $viewed_on
 * @property int $views
 * @property Perfume $perfume
 */
class PerfumeView extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'perfume_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_on' => 'immutable_date',
            'views' => 'integer',
        ];
    }

    /**
     * Count one view of a perfume against today.
     *
     * A single upsert rather than read-then-write, so two visitors landing at
     * the same moment both count instead of one overwriting the other.
     */
    public static function record(Perfume $perfume): void
    {
        static::query()->upsert(
            [['perfume_id' => $perfume->id, 'viewed_on' => now()->toDateString(), 'views' => 1]],
            ['perfume_id', 'viewed_on'],
            ['views' => DB::raw('views + 1')],
        );
    }

    /**
     * @return BelongsTo<Perfume, $this>
     */
    public function perfume(): BelongsTo
    {
        return $this->belongsTo(Perfume::class);
    }
}
