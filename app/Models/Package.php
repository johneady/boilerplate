<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Shop\Money;
use App\Shop\Region;
use Carbon\CarbonImmutable;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A package of drone footage filmed at one location, sold as a licence.
 *
 * Each location is its own package: the clips, the resolution they were shot
 * at, the price and how many licences remain. Stock is optional -- null sells
 * without limit, a number is a cap that checkout decrements.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $location
 * @property string $country
 * @property Region $region
 * @property string $summary
 * @property string|null $description
 * @property int $price_cents
 * @property string $resolution
 * @property int $frame_rate
 * @property int $clip_count
 * @property int $duration_seconds
 * @property int|null $stock
 * @property bool $is_active
 * @property bool $is_featured
 * @property string|null $image_path
 * @property string|null $image_credit
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'slug', 'title', 'location', 'country', 'region', 'summary', 'description', 'price_cents',
    'resolution', 'frame_rate', 'clip_count', 'duration_seconds', 'stock', 'is_active', 'is_featured',
    'image_path', 'image_credit', 'sort_order',
])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
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
            'price_cents' => 'integer',
            'frame_rate' => 'integer',
            'clip_count' => 'integer',
            'duration_seconds' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
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
     * The attribute that identifies a package in a URL.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Every order line this package has been sold on.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Limit the query to packages listed in the storefront.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Order the query the way the storefront lists packages.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Whether a customer can buy this package right now.
     */
    public function isAvailable(): bool
    {
        return $this->is_active && ! $this->isSoldOut();
    }

    /**
     * Whether a limited package has no licences left.
     */
    public function isSoldOut(): bool
    {
        return $this->stock !== null && $this->stock <= 0;
    }

    /**
     * Whether a limited package is down to its last few licences.
     */
    public function isLowStock(): bool
    {
        return $this->stock !== null
            && $this->stock > 0
            && $this->stock <= (int) config('shop.low_stock_threshold');
    }

    /**
     * The price, formatted in the shop's currency.
     */
    public function formattedPrice(): string
    {
        return Money::format($this->price_cents);
    }

    /**
     * The total running time of the clips, as minutes and seconds.
     */
    public function formattedDuration(): string
    {
        return sprintf('%d:%02d', intdiv($this->duration_seconds, 60), $this->duration_seconds % 60);
    }

    /**
     * The URL of the package's cover image, or null when it has none.
     *
     * Root-relative rather than absolute, like page body images: the public
     * disk's own url() bakes in APP_URL, which breaks the moment the same
     * database is served from another host.
     */
    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        return '/storage/'.ltrim($this->image_path, '/');
    }

    /**
     * Render the description to HTML.
     *
     * Escaped exactly as Page::renderedBody() is, and for the same reason: the
     * result is echoed unescaped, so any HTML in the source must be rendered
     * as text and javascript: links dropped. See .ai/rules/resources-pages.md.
     */
    public function renderedDescription(): string
    {
        $description = (string) $this->description;

        if (trim($description) === '') {
            return '';
        }

        return Str::markdown($description, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
