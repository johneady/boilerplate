<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of blog posts, browsable at /blog/category/{slug}.
 *
 * Flat by design: a hierarchy of categories is a content-strategy decision a
 * boilerplate should not make, and one level covers the demo's needs.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * The attribute that identifies a category in a URL.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Limit the query to categories with at least one published post.
     *
     * The blog's category row links to somewhere a visitor can go: a category
     * whose posts are all drafts would be a link to an empty page.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withPublishedPosts(Builder $query): void
    {
        $query->whereHas('posts', fn (Builder $post): Builder => $post->published());
    }
}
