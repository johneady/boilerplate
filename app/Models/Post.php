<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\RendersMarkdownBody;
use App\Media\HoldsMedia;
use App\Media\MediaCollection;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A blog post, written in the admin panel and published under /blog.
 *
 * Publishing is one column: published_at. Null is a draft, a future date is
 * scheduled, a past date is live -- so a scheduled post goes live at its
 * timestamp with no scheduler needed to move it; the published() scope simply
 * compares against now(), and everything that lists posts (the index, the
 * archives, the feed, the sitemap) agrees with everything else.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property string|null $seo_description
 * @property CarbonImmutable|null $published_at
 * @property int|null $author_id
 * @property int|null $category_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property User|null $author
 * @property Category|null $category
 * @property Collection<int, Tag> $tags
 */
#[Fillable(['title', 'slug', 'body', 'seo_description', 'published_at', 'author_id', 'category_id'])]
class Post extends Model implements HoldsMedia
{
    /** @use HasFactory<PostFactory> */
    use Auditable, HasFactory, HasMedia, RendersMarkdownBody;

    /**
     * Slugs a post may not claim.
     *
     * Only `feed` is a real collision: /blog/feed is a route of its own, so a
     * post saved there could never be reached. `category` and `tag` do not
     * collide -- the archives live one segment deeper, at /blog/category/{x}
     * -- but are reserved on purpose: a post at /blog/category beside the
     * /blog/category/... archives reads as a broken index page, not a post.
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = [
        'category',
        'feed',
        'tag',
    ];

    /**
     * The words per minute a reading time is estimated at.
     *
     * A constant rather than a setting: it produces a rough "how long is
     * this" hint, and the difference between 200 and 220 wpm is not a
     * decision an administrator should be asked to make.
     */
    private const int READING_WORDS_PER_MINUTE = 200;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * The attribute that identifies a post in a URL.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The user credited as the post's author.
     *
     * Nullable by design: a deleted author's posts keep their URLs, and the
     * byline falls back to the business name rather than the row vanishing.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Limit the query to posts visible to the public right now.
     *
     * A draft (null) and a scheduled post (future) are both excluded, and the
     * comparison runs against now() at query time -- which is the whole
     * publishing mechanism. See the class docblock.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Limit the query to posts waiting on a future publish date.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query->where('published_at', '>', now());
    }

    /**
     * Limit the query to posts with no publish date at all.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->whereNull('published_at');
    }

    /**
     * Whether the post is live for the public right now.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /**
     * Whether the post is waiting on a future publish date.
     */
    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /**
     * Whether the post has never been given a publish date.
     */
    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    /**
     * The post's state as a word: draft, scheduled or published.
     *
     * Derived from published_at rather than stored: the state changes by
     * itself the moment a scheduled date arrives, so a column would drift
     * from the truth without a scheduler rewriting it.
     */
    public function status(): string
    {
        if ($this->isDraft()) {
            return 'draft';
        }

        return $this->isScheduled() ? 'scheduled' : 'published';
    }

    protected function bodyCacheKeyPrefix(): string
    {
        return 'post-body';
    }

    /**
     * A one-or-two-sentence summary of the post for cards and the feed.
     *
     * The search description when one is written, since it exists for exactly
     * this job; the body's opening otherwise. The body is Markdown, so its
     * opening is taken from the rendered (cached) HTML with the markup
     * stripped -- otherwise a card would print "## Heading" and "**bold**"
     * literally. The result is plain text, escaped by the caller.
     */
    public function excerpt(): string
    {
        $description = trim((string) $this->seo_description);

        if ($description !== '') {
            return $description;
        }

        $text = html_entity_decode(strip_tags($this->renderedBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(Str::squish($text), 160);
    }

    /**
     * When the post last changed as far as a feed reader is concerned.
     *
     * The later of the last save and the publish date: a scheduled post is
     * saved before it goes live, so its updated_at alone would predate its
     * own publication and a reader would see an entry "updated" before it
     * was published.
     */
    public function feedUpdatedAt(): CarbonImmutable
    {
        $updatedAt = $this->updated_at ?? CarbonImmutable::now();

        if ($this->published_at === null) {
            return $updatedAt;
        }

        return $updatedAt->max($this->published_at);
    }

    /**
     * The post's estimated reading time in minutes.
     */
    public function readingTime(): int
    {
        $words = str_word_count(strip_tags((string) $this->body));

        // At least a minute: a "0 min read" on a short note reads as broken,
        // and anything under a minute is honestly "a minute".
        return max(1, (int) ceil($words / self::READING_WORDS_PER_MINUTE));
    }

    /**
     * A URL for the post's cover image, or null when there is none.
     *
     * Null also while a just-uploaded cover is still being processed (see
     * HasMedia::mediaUrl), so a caller renders a placeholder rather than a
     * broken image -- the same contract an avatar follows.
     */
    public function coverUrl(?string $conversion = null): ?string
    {
        return $this->mediaUrl(MediaCollection::PostCover, $conversion);
    }

    /**
     * The published post that ran immediately before this one.
     *
     * Neighbours are among published posts only, ordered by publish date:
     * a draft sitting between two live posts must not become "next" for a
     * visitor the day it is published out of order.
     */
    public function previousPost(): ?self
    {
        return $this->neighbour('desc');
    }

    /**
     * The published post that ran immediately after this one.
     */
    public function nextPost(): ?self
    {
        return $this->neighbour('asc');
    }

    /**
     * The nearest published neighbour in the given direction.
     *
     * @param  'asc'|'desc'  $direction
     */
    private function neighbour(string $direction): ?self
    {
        if ($this->published_at === null) {
            return null;
        }

        $operator = $direction === 'asc' ? '>' : '<';

        // (published_at, id) compared as a pair, so id breaks a tie: two
        // posts sharing a publish date must neither skip each other nor be
        // shown twice. A strict comparison on published_at alone would drop
        // every post sharing this one's timestamp from the navigation.
        return self::query()
            ->published()
            ->where(fn (Builder $query): Builder => $query
                ->where('published_at', $operator, $this->published_at)
                ->orWhere(fn (Builder $tie): Builder => $tie
                    ->where('published_at', $this->published_at)
                    ->where('id', $operator, $this->getKey())))
            ->orderBy('published_at', $direction)
            ->orderBy('id', $direction)
            ->first();
    }

    /**
     * Recent published posts besides this one, for the "more posts" row.
     *
     * Related-by-category drifts from what a visitor expects the moment a
     * category holds more than a handful of posts: two of the three
     * "related" links were then older posts nobody came for. The blog's
     * freshest other posts are what a reader finishing this post is most
     * plausibly next in line for. The caller passes the prev/next posts it
     * already links as $excludeIds, so the same post is not offered twice.
     *
     * @param  list<int>  $excludeIds
     * @return Collection<int, self>
     */
    public function relatedPosts(int $limit = 2, array $excludeIds = []): Collection
    {
        return self::query()
            ->published()
            ->with(['author', 'category', 'tags', 'media'])
            ->whereKeyNot([$this->getKey(), ...$excludeIds])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
