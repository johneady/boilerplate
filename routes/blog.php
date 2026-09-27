<?php

use App\Http\Controllers\Blog\BlogCategoryController;
use App\Http\Controllers\Blog\BlogFeedController;
use App\Http\Controllers\Blog\BlogIndexController;
use App\Http\Controllers\Blog\BlogPostController;
use App\Http\Controllers\Blog\BlogTagController;
use App\Http\Middleware\EnsureBlogEnabled;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Blog
|--------------------------------------------------------------------------
|
| Every path starts with /blog, so none is a single path segment and none can
| collide with an administrator's content page slug ('blog' itself is in
| Page::RESERVED_SLUGS).
|
| Switching the blog off closes all of these at once (EnsureBlogEnabled
| answers 404): unlike payments there is nothing "in flight" to keep serving,
| because publishing a post has no counterpart to a payment already taken.
|
| Route order matters within the group: the archive and feed routes sit on
| the same depth as a post, so they are registered before /blog/{post} --
| and Post::RESERVED_SLUGS stops a post claiming one of their segments
| anyway.
|
*/

Route::middleware(EnsureBlogEnabled::class)->group(function (): void {
    Route::get('blog', BlogIndexController::class)->name('blog.index');

    Route::get('blog/category/{category}', BlogCategoryController::class)
        ->where('category', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('blog.category');

    Route::get('blog/tag/{tag}', BlogTagController::class)
        ->where('tag', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('blog.tag');

    Route::get('blog/feed', BlogFeedController::class)->name('blog.feed');

    Route::get('blog/{post}', BlogPostController::class)
        ->where('post', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('blog.show');
});
