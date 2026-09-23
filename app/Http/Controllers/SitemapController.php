<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Http\Response;

/**
 * Serves /sitemap.xml, listing the site's publicly crawlable pages.
 *
 * Generated per request rather than written to public/ by a command: the file
 * has to reflect the AllowSearchIndexing setting, which an administrator can
 * change at any moment, and the page list is small enough that rendering it
 * costs less than the machinery to keep a cached copy in step. A site that
 * grows past a few hundred URLs should revisit that trade, not this shape.
 */
class SitemapController extends Controller
{
    /**
     * The named routes offered to crawlers, each with its change frequency.
     *
     * Deliberately an explicit list rather than a sweep of the route table:
     * most registered routes are behind auth, are form POST targets, or are
     * the dev-only previews, and a crawler must see none of them. Adding a
     * public marketing page means adding it here -- which is the point.
     *
     * @var array<string, array{changefreq: string, priority: string}>
     */
    public const array ROUTES = [
        'home' => ['changefreq' => 'weekly', 'priority' => '1.0'],
        'order' => ['changefreq' => 'monthly', 'priority' => '0.8'],
        'contact' => ['changefreq' => 'yearly', 'priority' => '0.5'],
    ];

    /**
     * The change frequency and priority given to an administrator's pages.
     *
     * One shared value rather than a per-page setting: the distinction between
     * 0.4 and 0.5 is not one an administrator should be asked to make, and
     * crawlers treat priority as a hint between a site's own URLs at best.
     *
     * @var array{changefreq: string, priority: string}
     */
    private const array PAGE_DEFAULTS = ['changefreq' => 'yearly', 'priority' => '0.5'];

    /**
     * Render the sitemap.
     *
     * When indexing is off the sitemap is still served, but empty: a 404 would
     * leave a previously submitted sitemap erroring in Search Console, whereas
     * an empty urlset says plainly that there is nothing to crawl today.
     */
    public function __invoke(Settings $settings): Response
    {
        $urls = $settings->boolean(SettingKey::AllowSearchIndexing)
            ? $this->urls()
            : [];

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * The absolute URL and metadata for each crawlable page.
     *
     * There are two sources here, and the split is deliberate. ROUTES above
     * covers the pages that are part of the application -- they exist in every
     * deployment, so they are named in code. The administrator's content pages
     * cannot be: they are rows, and which of them exist is a decision made in
     * the panel after deployment, so they are read from the table.
     *
     * Only published pages are listed. A draft is a 404 to the public (see
     * PageController), and pointing a crawler at a URL that 404s is worse than
     * omitting it.
     *
     * @return list<array{loc: string, changefreq: string, priority: string}>
     */
    private function urls(): array
    {
        $urls = [];

        foreach (self::ROUTES as $name => $meta) {
            $urls[] = [
                'loc' => route($name),
                'changefreq' => $meta['changefreq'],
                'priority' => $meta['priority'],
            ];
        }

        // Keyed by URL while building, so a page whose slug matches one of the
        // named routes above appears once rather than twice. The seeded
        // 'contact' page is exactly that case: it supplies the copy shown above
        // the form on route('contact'), so both resolve to the same URL, and a
        // sitemap listing one URL twice is a malformed sitemap.
        $urls = array_column($urls, null, 'loc');

        foreach (Page::query()->published()->orderBy('sort_order')->get(['id', 'slug']) as $page) {
            $loc = route('pages.show', $page);

            if (array_key_exists($loc, $urls)) {
                continue;
            }

            $urls[$loc] = [
                'loc' => $loc,
                'changefreq' => self::PAGE_DEFAULTS['changefreq'],
                'priority' => self::PAGE_DEFAULTS['priority'],
            ];
        }

        return array_values($urls);
    }
}
