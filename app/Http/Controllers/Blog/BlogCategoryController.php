<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One category's archive: its published posts, newest first.
 *
 * A category with no published posts renders an empty archive rather than a
 * 404 -- it exists, and the empty state is honest about it.
 */
class BlogCategoryController extends Controller
{
    public function __invoke(Request $request, Category $category): View
    {
        return view('blog.archive', [
            'heading' => $category->name,
            'description' => __('Posts filed under :name.', ['name' => $category->name]),
            'posts' => $category->posts()
                ->published()
                ->with(['author', 'category', 'tags', 'media'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->simplePaginate(BlogIndexController::POSTS_PER_PAGE),
        ]);
    }
}
