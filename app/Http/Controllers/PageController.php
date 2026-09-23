<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the administrator-authored public content pages.
 *
 * Reached through the fallback route in routes/web.php, which runs only after
 * every other route has failed to match. See the note there for why it is a
 * fallback rather than an ordinary catch-all, and Page::RESERVED_SLUGS for how a
 * page is stopped from claiming a slug a real route answers on.
 */
class PageController extends Controller
{
    /**
     * Pages rendered through a designed template of their own rather than the
     * plain article one, keyed by slug.
     *
     * The body is still the administrator's Markdown, rendered and escaped by
     * Page::renderedBody() exactly as on any other page -- the template only
     * adds the photography and calls to action around it.
     *
     * @var array<string, string>
     */
    private const array TEMPLATES = [
        'about' => 'pages.about',
    ];

    /**
     * Render a page.
     *
     * The slug is read from the route rather than arriving as a bound Page,
     * because a fallback route names its parameter 'fallbackPlaceholder' and
     * implicit binding has nothing to key on. Resolved here instead, which also
     * keeps the missing-page 404 in the same method as the draft one.
     *
     * A draft is a 404 for the public but renders for anyone who may edit it, so
     * the panel's "view" link works before publishing -- there is otherwise no
     * way to see how a page reads until it is already live. Authorized through
     * the Gate rather than an is_admin check, so the rule follows PagePolicy if
     * a third role ever gains page editing.
     *
     * The 404 is deliberately indistinguishable from a page that does not exist:
     * a different status or message would let anybody enumerate which drafts are
     * being written.
     */
    public function __invoke(Request $request, string $fallbackPlaceholder): Response
    {
        $page = Page::query()->where('slug', $fallbackPlaceholder)->first();

        abort_if($page === null, 404);

        abort_unless(
            $page->is_published || $request->user()?->can('update', $page),
            404,
        );

        return response()->view(self::TEMPLATES[$page->slug] ?? 'pages.show', ['page' => $page]);
    }
}
