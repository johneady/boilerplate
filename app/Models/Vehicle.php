<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Voltiva\Money;
use App\Voltiva\VehicleCategory;
use Carbon\CarbonImmutable;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One car in the Voltiva range.
 *
 * Every car is rendered by the same product template (cars/show) and compared
 * on the same page, both reading the columns below -- so adding a car is a row
 * filled in the admin panel, never a new design, and a price changed here
 * changes everywhere it is shown.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property VehicleCategory $category
 * @property string $tagline
 * @property string $summary
 * @property string|null $description
 * @property int $price_cents
 * @property int|null $monthly_from_cents
 * @property int $top_speed_kmh
 * @property int $range_km
 * @property int $battery_voltage
 * @property int $battery_capacity_ah
 * @property string $battery_kwh
 * @property string $battery_chemistry
 * @property string $charge_hours
 * @property string $motor_kw
 * @property int $seats
 * @property int $length_mm
 * @property int $width_mm
 * @property int $height_mm
 * @property int $kerb_weight_kg
 * @property int $warranty_years
 * @property int $battery_warranty_years
 * @property list<string>|null $equipment
 * @property list<array{title: string, body: string}>|null $key_benefits
 * @property string|null $comfort
 * @property string|null $safety
 * @property list<array{question: string, answer: string}>|null $faqs
 * @property string|null $image_path
 * @property list<string>|null $gallery
 * @property string|null $video_url
 * @property string|null $image_credit
 * @property string|null $seo_description
 * @property bool $is_published
 * @property bool $is_featured
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'slug', 'name', 'category', 'tagline', 'summary', 'description', 'price_cents', 'monthly_from_cents',
    'top_speed_kmh', 'range_km', 'battery_voltage', 'battery_capacity_ah', 'battery_kwh', 'battery_chemistry',
    'charge_hours', 'motor_kw', 'seats', 'length_mm', 'width_mm', 'height_mm', 'kerb_weight_kg',
    'warranty_years', 'battery_warranty_years', 'equipment', 'key_benefits', 'comfort', 'safety', 'faqs',
    'image_path', 'gallery', 'video_url', 'image_credit', 'seo_description', 'is_published', 'is_featured',
    'sort_order',
])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use Auditable, HasFactory;

    /**
     * Slugs a car may not take, because the category listings share the
     * /cars/{segment} space with the product pages.
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = ['l6e', 'l7e'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => VehicleCategory::class,
            'price_cents' => 'integer',
            'monthly_from_cents' => 'integer',
            'battery_kwh' => 'decimal:1',
            'charge_hours' => 'decimal:1',
            'motor_kw' => 'decimal:1',
            'equipment' => 'array',
            'key_benefits' => 'array',
            'faqs' => 'array',
            'gallery' => 'array',
            'is_published' => 'boolean',
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
     * The attribute that identifies a car in a URL.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Enquiries made about this car.
     *
     * @return HasMany<Enquiry, $this>
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    /**
     * Articles written about this car.
     *
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Limit the query to cars shown on the public site.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Order the query the way the range is listed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The price, formatted for the visitor's language.
     */
    public function formattedPrice(): string
    {
        return Money::format($this->price_cents);
    }

    /**
     * The "from" monthly finance figure, or null when none is advertised.
     */
    public function formattedMonthlyFrom(): ?string
    {
        return $this->monthly_from_cents === null ? null : Money::format($this->monthly_from_cents);
    }

    /**
     * Length × width × height in metres, e.g. "2.53 × 1.39 × 1.57 m".
     */
    public function formattedDimensions(): string
    {
        return sprintf(
            '%s × %s × %s m',
            number_format($this->length_mm / 1000, 2),
            number_format($this->width_mm / 1000, 2),
            number_format($this->height_mm / 1000, 2),
        );
    }

    /**
     * The battery described the way the brief asks: capacity first, with the
     * units a customer can look up -- "72V 150Ah LiFePO4".
     */
    public function batteryDescription(): string
    {
        return sprintf('%dV %dAh %s', $this->battery_voltage, $this->battery_capacity_ah, $this->battery_chemistry);
    }

    /**
     * A decimal column as a short number: "10.8" stays, "6.0" becomes "6".
     */
    public function figure(string $attribute): string
    {
        $value = (string) $this->getAttribute($attribute);

        // Only a decimal part is trimmed: rtrim on "100" would give "1".
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    /**
     * The main photograph's URL, or null when none is uploaded.
     *
     * Root-relative rather than absolute, like page body images: the public
     * disk's url() bakes in APP_URL, which breaks when the same database is
     * served from another host.
     */
    public function imageUrl(): ?string
    {
        return self::publicUrl($this->image_path);
    }

    /**
     * The gallery photographs' URLs.
     *
     * @return list<string>
     */
    public function galleryUrls(): array
    {
        return array_values(array_filter(array_map(
            fn (string $path): ?string => self::publicUrl($path),
            $this->gallery ?? [],
        )));
    }

    /**
     * The privacy-friendly embed URL for the video, or null when there is no
     * video or it is not on a supported host.
     *
     * YouTube and Vimeo links are accepted in any of their usual shapes and
     * turned into an embed URL here, so an editor pastes the link from the
     * address bar and the template's fixed 16:9 video area does the rest.
     */
    public function videoEmbedUrl(): ?string
    {
        $url = (string) $this->video_url;

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $url, $matches) === 1) {
            return 'https://www.youtube-nocookie.com/embed/'.$matches[1].'?autoplay=1&rel=0';
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $matches) === 1) {
            return 'https://player.vimeo.com/video/'.$matches[1].'?autoplay=1&dnt=1';
        }

        return null;
    }

    /**
     * Render the long description to HTML, escaped exactly as a page body is.
     * See Page::renderedBody() and .ai/rules/resources-pages.md.
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

    /**
     * Schema.org data for the product page, as an array the head encodes.
     *
     * @return array<string, mixed>
     */
    public function structuredData(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Car',
            'name' => $this->name,
            'description' => $this->summary,
            'image' => $this->imageUrl() === null ? null : url($this->imageUrl()),
            'url' => route('cars.show', $this),
            'vehicleSeatingCapacity' => $this->seats,
            'fuelType' => 'Electric',
            'speed' => ['@type' => 'QuantitativeValue', 'maxValue' => $this->top_speed_kmh, 'unitCode' => 'KMH'],
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($this->price_cents / 100, 2, '.', ''),
                'priceCurrency' => (string) config('voltiva.currency'),
                'availability' => 'https://schema.org/InStock',
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * A root-relative URL for a file on the public disk.
     */
    public static function publicUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return '/storage/'.ltrim((string) $path, '/');
    }
}
