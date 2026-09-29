<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A perfume house. Created on the fly by the importer the first time a data
 * file names it, so a refresh never needs brands set up by hand first.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $country
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property Collection<int, Perfume> $perfumes
 */
#[Fillable(['name', 'slug', 'country'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Perfume, $this>
     */
    public function perfumes(): HasMany
    {
        return $this->hasMany(Perfume::class);
    }
}
