<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Travel\Region;
use Carbon\CarbonImmutable;
use Database\Factories\DestinationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place the agency runs tours to, with its own landing page.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $country
 * @property Region $region
 * @property string $tagline
 * @property string $description
 * @property string|null $best_time
 * @property string|null $image_path
 * @property string|null $image_credit
 * @property bool $is_featured
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'slug', 'name', 'country', 'region', 'tagline', 'description', 'best_time',
    'image_path', 'image_credit', 'is_featured', 'sort_order',
])]
class Destination extends Model
{
    /** @use HasFactory<DestinationFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region' => Region::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Destination pages are addressed by slug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Tour, $this>
     */
    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    /**
     * Order the query the way the site lists destinations.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * "Tuscany, Italy", or just "Iceland" where the place is the country.
     */
    public function place(): string
    {
        return $this->name === $this->country ? $this->name : $this->name.', '.$this->country;
    }

    /**
     * The URL of the destination's photo, or null when it has none.
     *
     * Root-relative rather than absolute: the public disk's own url() bakes in
     * APP_URL, which breaks the moment the same database is served from
     * another host.
     */
    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        return '/storage/'.ltrim($this->image_path, '/');
    }
}
