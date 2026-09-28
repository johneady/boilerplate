<?php

namespace App\Http\Middleware;

use App\Blog\BlogManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer 404 on every blog route while the blog is switched off.
 *
 * The routes stay registered -- a setting cannot decide route registration
 * under the route cache, and route('blog.index') must keep resolving -- and
 * this turns them away at runtime, exactly as EnsurePaymentsEnabled does for
 * payments. A 404 rather than a 403, so an installation with the blog off
 * looks exactly like one without the module.
 *
 * Nothing is destroyed by switching the blog off: posts stay in their table
 * and come back unchanged when it is turned on again.
 */
class EnsureBlogEnabled
{
    public function __construct(private readonly BlogManager $blog) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->blog->enabled(), 404);

        return $next($request);
    }
}
