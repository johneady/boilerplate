<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Renders a model's Markdown `body` column to HTML, cached per save.
 *
 * Shared by Page and Post so the escaping below is written once: a hardening
 * applied to one body renderer and missed on the other would leave a second
 * stored-XSS path open.
 */
trait RendersMarkdownBody
{
    /**
     * How long a rendered body stays cached.
     *
     * A TTL rather than forever, and that is load-bearing. The cache key carries
     * updated_at, so every save writes a NEW key and abandons the old one --
     * nothing reads it again and nothing deletes it. The default store here is
     * `database`, and app:prune-expired-storage deletes rows by `expiration`, so
     * a forever entry is a row no code path can reclaim: an edited record would
     * leave one orphan per save in the cache table, permanently. With a TTL the
     * orphan ages out and the scheduled prune collects it.
     */
    private const int BODY_CACHE_TTL_SECONDS = 604800;

    /**
     * The prefix naming this model's rendered bodies in the cache.
     */
    abstract protected function bodyCacheKeyPrefix(): string;

    /**
     * Render the body to HTML.
     *
     * The body is administrator-authored text echoed into the page with `{!! !!}`,
     * so this method is the one thing standing between the database and unescaped
     * output. Both options are load-bearing and must not be removed:
     *
     * - `html_input => 'escape'` renders any HTML in the source as literal text.
     *   Without it a stored `<script>` executes for every visitor, which turns
     *   the admin panel's editor into stored XSS -- reachable by anyone who can
     *   edit the record, and by anything that can write to the row.
     * - `allow_unsafe_links => false` drops `javascript:` and `data:` URLs, which
     *   are the same attack wearing a Markdown link.
     *
     * Markdown rather than a rich-text editor storing HTML is the decision these
     * options implement: nothing else in this application renders unescaped
     * administrator input, and this feature was not the place to start.
     *
     * Cached on the row's updated_at, so a save rolls the key rather than
     * needing invalidation -- editing shows the new copy immediately and the
     * entry the edit orphaned expires on its own -- see BODY_CACHE_TTL_SECONDS
     * for why that TTL is not `forever`.
     */
    public function renderedBody(): string
    {
        $body = (string) $this->body;

        if (trim($body) === '') {
            return '';
        }

        return Cache::remember(
            $this->bodyCacheKey(),
            self::BODY_CACHE_TTL_SECONDS,
            fn (): string => Str::markdown($body, [
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]),
        );
    }

    /**
     * The cache key holding this record's rendered body.
     *
     * Keyed on the timestamp as well as the id so any save produces a new key.
     * A record with no updated_at (one built but never stored) falls back to a
     * value that cannot collide with a saved row -- note every UNSAVED
     * instance shares that one key, so a caller rendering previews of several
     * unsaved records must not cache through this path.
     */
    private function bodyCacheKey(): string
    {
        $version = $this->updated_at?->getTimestamp() ?? 'unsaved';

        return sprintf('%s:%s:%s', $this->bodyCacheKeyPrefix(), $this->getKey() ?? 'new', $version);
    }
}
