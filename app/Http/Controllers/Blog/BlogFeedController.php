<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * Serves /blog/feed, the blog's Atom feed.
 *
 * Rendered per request rather than cached: it follows the BlogEnabled switch
 * at the route and the published scope here, both of which can change at any
 * moment, and a blog small enough to need caching is a blog whose feed is
 * cheap to render.
 */
class BlogFeedController extends Controller
{
    /**
     * How many entries the feed carries.
     *
     * Atom readers keep their own copy, so the feed needs the newest posts --
     * enough to fill a reader's first view -- not every post ever written.
     */
    private const int FEED_LIMIT = 20;

    public function __invoke(Settings $settings): Response
    {
        $posts = Post::query()
            ->published()
            ->with('author')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::FEED_LIMIT)
            ->get();

        return response()
            ->view('blog.feed', [
                'posts' => $posts,
                'blogName' => $settings->businessName(),
                'feedId' => route('blog.index'),
                'feedUrl' => route('blog.index'),
                'selfUrl' => url()->current(),
                // The feed-level updated timestamp: the latest of every
                // entry's own, or now for an empty feed -- computed here
                // rather than in the template, which has no reason to know
                // the rule. The newest-PUBLISHED entry is not necessarily
                // the most recently edited one.
                'updated' => $posts->map(fn (Post $post): CarbonImmutable => $post->feedUpdatedAt())->max() ?? now(),
            ])
            ->header('Content-Type', 'application/atom+xml; charset=utf-8');
    }
}
