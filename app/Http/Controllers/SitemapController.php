<?php

namespace App\Http\Controllers;

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
    ];

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
     * The absolute URL and metadata for each listed route.
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

        return $urls;
    }
}
