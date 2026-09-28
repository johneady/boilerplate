<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The blog's front page: the newest published posts, ten at a time.
 *
 * Eager-loads everything a card renders -- the author's name, the category
 * badge, the tags and the cover -- because a page of ten cards is otherwise
 * forty queries wearing one view.
 */
class BlogIndexController extends Controller
{
    /**
     * The most posts a page of the listing shows.
     *
     * A constant rather than a setting: it pairs with the card grid's layout,
     * and "how many cards fit" is not a decision an administrator needs a
     * settings field for.
     */
    public const int POSTS_PER_PAGE = 10;

    public function __invoke(Request $request): View
    {
        return view('blog.index', [
            'posts' => Post::query()
                ->published()
                ->with(['author', 'category', 'tags', 'media'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->simplePaginate(self::POSTS_PER_PAGE)
                ->withQueryString(),
            'categories' => Category::query()
                ->withPublishedPosts()
                ->orderBy('name')
                ->get(['id', 'slug', 'name']),
        ]);
    }
}
