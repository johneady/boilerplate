<?php

namespace App\Models;

use App\Perfumes\Enums\ImportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of a data refresh: which file, who ran it, and what changed.
 *
 * @property int $id
 * @property string $file_name
 * @property ImportStatus $status
 * @property int $rows_total
 * @property int $rows_created
 * @property int $rows_updated
 * @property int $rows_unchanged
 * @property int $rows_failed
 * @property array<int|string, string>|null $errors
 * @property int|null $user_id
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property User|null $user
 */
#[Fillable([
    'file_name', 'status', 'rows_total', 'rows_created', 'rows_updated', 'rows_unchanged', 'rows_failed',
    'errors', 'user_id', 'finished_at',
])]
class PerfumeImport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'errors' => 'array',
            'finished_at' => 'immutable_datetime',
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
