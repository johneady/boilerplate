<?php

namespace App\Models;

use App\Perfumes\Enums\Audience;
use App\Perfumes\Enums\Concentration;
use App\Perfumes\Enums\Family;
use Carbon\CarbonImmutable;
use Database\Factories\PerfumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One fragrance in the public database.
 *
 * Catalogue fields come from the data files (see PerfumeImporter) and are
 * keyed on external_id; followers and view history are the site's own and
 * hang off the local id, so a refresh never touches them.
 *
 * @property int $id
 * @property string $external_id
 * @property int $brand_id
 * @property string $name
 * @property string $slug
 * @property int|null $release_year
 * @property Concentration|null $concentration
 * @property Audience|null $gender
 * @property Family|null $family
 * @property string|null $perfumer
 * @property list<string>|null $top_notes
 * @property list<string>|null $heart_notes
 * @property list<string>|null $base_notes
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property Brand $brand
 * @property Collection<int, User> $followers
 * @property int|null $followers_count
 */
#[Fillable([
    'external_id', 'brand_id', 'name', 'slug', 'release_year', 'concentration', 'gender', 'family',
    'perfumer', 'top_notes', 'heart_notes', 'base_notes', 'description',
])]
class Perfume extends Model
{
    /** @use HasFactory<PerfumeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_year' => 'integer',
            'concentration' => Concentration::class,
            'gender' => Audience::class,
            'family' => Family::class,
            'top_notes' => 'array',
            'heart_notes' => 'array',
            'base_notes' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'perfume_follows')->withPivot('created_at');
    }

    /**
     * @return HasMany<PerfumeView, $this>
     */
    public function dailyViews(): HasMany
    {
        return $this->hasMany(PerfumeView::class);
    }

    /**
     * Every note in the pyramid, top to base, without repeats.
     *
     * @return list<string>
     */
    public function allNotes(): array
    {
        return array_values(array_unique([
            ...($this->top_notes ?? []),
            ...($this->heart_notes ?? []),
            ...($this->base_notes ?? []),
        ]));
    }

    /**
     * Match a free-text search against the name, the house, the perfumer and
     * the notes, so "vanilla" finds every perfume with a vanilla accord.
     *
     * The notes are matched as JSON text: a LIKE over the encoded array works
     * the same on SQLite and MariaDB, where a JSON-path search would not.
     *
     * @param  Builder<Perfume>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('perfumer', 'like', $like)
                ->orWhere('top_notes', 'like', $like)
                ->orWhere('heart_notes', 'like', $like)
                ->orWhere('base_notes', 'like', $like)
                ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like));
        });
    }
}
