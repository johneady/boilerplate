<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One tag's archive: the published posts carrying it, newest first.
 */
class BlogTagController extends Controller
{
    public function __invoke(Request $request, Tag $tag): View
    {
        return view('blog.archive', [
            'heading' => $tag->name,
            'description' => __('Posts tagged :name.', ['name' => $tag->name]),
            'posts' => $tag->posts()
                ->published()
                ->with(['author', 'category', 'tags', 'media'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->simplePaginate(BlogIndexController::POSTS_PER_PAGE),
        ]);
    }
}
