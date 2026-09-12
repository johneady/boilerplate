<?php

namespace App\Http\Controllers;

use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Http\Response;

/**
 * Serves /robots.txt from the AllowSearchIndexing setting.
 *
 * This replaces the static public/robots.txt, which could not know about the
 * setting: turning indexing off left the meta robots tag saying "noindex"
 * while robots.txt still invited crawlers over the whole site. The two must
 * agree, so they now come from the same value.
 *
 * Note that public/robots.txt must stay deleted. The web server matches a real
 * file before it reaches PHP, so leaving one on disk would silently shadow
 * this route and restore the mismatch.
 */
class RobotsController extends Controller
{
    /**
     * Render robots.txt.
     *
     * "Disallow: /" is a request not to crawl, not an access control -- it is
     * the companion to the noindex meta tag rather than a substitute for
     * authentication, which is what actually keeps the signed-in area private.
     */
    public function __invoke(Settings $settings): Response
    {
        $lines = ['User-agent: *'];

        if ($settings->boolean(SettingKey::AllowSearchIndexing)) {
            $lines[] = 'Disallow:';
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
