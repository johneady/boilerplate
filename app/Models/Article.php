<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Voltiva\ArticleTopic;
use Carbon\CarbonImmutable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A News & Advice article.
 *
 * Every article renders through one template (news/show). The topic decides
 * which section of the site it links back to, and an optional car puts that
 * car's card at the foot of the article -- so an editor can publish without a
 * designer and the article still leads somewhere.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property ArticleTopic $topic
 * @property string $excerpt
 * @property string $body
 * @property int|null $vehicle_id
 * @property string|null $image_path
 * @property string|null $image_credit
 * @property string|null $seo_description
 * @property bool $is_published
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Vehicle|null $vehicle
 */
#[Fillable([
    'slug', 'title', 'topic', 'excerpt', 'body', 'vehicle_id', 'image_path', 'image_credit', 'seo_description',
    'is_published', 'published_at',
])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic' => ArticleTopic::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
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
     * The attribute that identifies an article in a URL.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The car this article is about, if any.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Limit the query to articles visible to the public: published, and not
     * scheduled for a date still to come.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    /**
     * Newest first.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    /**
     * Whether the public can read this article.
     */
    public function isVisible(): bool
    {
        return $this->is_published && ($this->published_at === null || $this->published_at->isPast());
    }

    /**
     * The cover photograph's URL, or null when none is uploaded.
     */
    public function imageUrl(): ?string
    {
        return Vehicle::publicUrl($this->image_path);
    }

    /**
     * A rough reading time, in minutes.
     */
    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->body)) / 200));
    }

    /**
     * Render the body to HTML, escaped exactly as a page body is. See
     * Page::renderedBody() and .ai/rules/resources-pages.md.
     */
    public function renderedBody(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Schema.org data for the article page, as an array the head encodes.
     *
     * @return array<string, mixed>
     */
    public function structuredData(string $publisher): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $this->title,
            'description' => $this->excerpt,
            'image' => $this->imageUrl() === null ? null : url($this->imageUrl()),
            'datePublished' => $this->published_at?->toAtomString(),
            'dateModified' => $this->updated_at?->toAtomString(),
            'mainEntityOfPage' => route('news.show', $this),
            'publisher' => ['@type' => 'Organization', 'name' => $publisher],
        ], fn (mixed $value): bool => $value !== null);
    }
}
