<?php

namespace App\Models;

use App\Bakery\DietaryTag;
use App\Bakery\MenuCategory;
use App\Bakery\Money;
use App\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing the bakery makes, as it appears on the menu.
 *
 * The menu is the home page, and the order form offers exactly the items that
 * are available here -- switching an item off in the panel ("sold out this
 * week") removes it from both at once.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property MenuCategory $category
 * @property string $description
 * @property int $price_cents
 * @property string $price_unit
 * @property string|null $serves
 * @property list<string>|null $dietary
 * @property int $notice_days
 * @property bool $is_available
 * @property bool $is_seasonal
 * @property bool $is_featured
 * @property string|null $image_path
 * @property string|null $image_credit
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'slug', 'name', 'category', 'description', 'price_cents', 'price_unit', 'serves', 'dietary',
    'notice_days', 'is_available', 'is_seasonal', 'is_featured', 'image_path', 'image_credit', 'sort_order',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => MenuCategory::class,
            'price_cents' => 'integer',
            'dietary' => 'array',
            'notice_days' => 'integer',
            'is_available' => 'boolean',
            'is_seasonal' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Attributes kept out of the audit trail beyond the global denylist.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['updated_at'];
    }

    /**
     * Limit the query to items customers can order right now.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_available', true);
    }

    /**
     * Order the query the way the menu lists items.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The dietary notes on this item, skipping any value no longer offered.
     *
     * @return list<DietaryTag>
     */
    public function dietaryTags(): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): ?DietaryTag => DietaryTag::tryFrom($value),
            $this->dietary ?? [],
        )));
    }

    /**
     * The price, formatted in the bakery's currency.
     */
    public function formattedPrice(): string
    {
        return Money::format($this->price_cents);
    }

    /**
     * The URL of the item's photo, or null when it has none.
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
