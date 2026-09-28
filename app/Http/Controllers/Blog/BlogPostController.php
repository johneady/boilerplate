<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves a single blog post.
 *
 * A draft or a scheduled post is a 404 for the public but renders for anyone
 * who may edit it, so the panel's "visit" link works before publishing --
 * the same contract PageController gives content pages.
 *
 * The 404 is deliberately indistinguishable from a post that does not exist:
 * a different status or message would let anybody enumerate which drafts are
 * being written and which posts are scheduled.
 */
class BlogPostController extends Controller
{
    public function __invoke(Request $request, Post $post): Response
    {
        abort_unless(
            $post->isPublished() || $request->user()?->can('update', $post),
            404,
        );

        return response()->view('blog.show', ['post' => $post]);
    }
}
