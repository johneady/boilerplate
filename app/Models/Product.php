<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Ordering\Price;
use App\Ordering\ProductCategory;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Something on the café's menu that customers can add to their cart.
 *
 * Switching is_available off in the admin panel ("sold out today") hides the
 * product from the menu and drops it from any cart it is sitting in.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property ProductCategory $category
 * @property string $description
 * @property int $price_cents
 * @property string|null $image_path
 * @property string|null $image_credit
 * @property bool $is_available
 * @property bool $is_featured
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'category', 'description', 'price_cents', 'image_path', 'image_credit', 'is_available', 'is_featured', 'sort_order'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'price_cents' => 'integer',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['updated_at'];
    }

    /**
     * Only products customers can order right now.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_available', true);
    }

    /**
     * The order the menu lists products in.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function formattedPrice(): string
    {
        return Price::format($this->price_cents);
    }

    /**
     * The photo's URL, or null when the product has none.
     *
     * Root-relative, so the same database works when served from another host.
     */
    public function imageUrl(): ?string
    {
        return blank($this->image_path) ? null : '/storage/'.ltrim((string) $this->image_path, '/');
    }
}
