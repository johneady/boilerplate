<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Media\HoldsMedia;
use Carbon\CarbonImmutable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A public content page an administrator writes in the admin panel.
 *
 * These are the pages every deployed site needs and no deployment can share --
 * a privacy policy naming a real company, terms written for one jurisdiction,
 * an about page. They are rows rather than Blade files or settings so an
 * administrator can add a Cookie Policy or a Refund Policy without a release,
 * which is the whole reason the feature is not three SettingKey cases.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $body
 * @property string|null $seo_description
 * @property bool $is_published
 * @property bool $show_in_footer
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['slug', 'title', 'body', 'seo_description', 'is_published', 'show_in_footer', 'sort_order'])]
class Page extends Model implements HoldsMedia
{
    /** @use HasFactory<PageFactory> */
    use Auditable, HasFactory, HasMedia;

    /**
     * Slugs a page may not claim, because a real route already answers on them.
     *
     * The public page route is a catch-all single segment registered last in
     * routes/web.php, so it only ever sees paths nothing else matched -- a page
     * slugged "login" would not shadow Fortify's login page, it would simply be
     * unreachable. That is the failure this list prevents: an administrator
     * saving a page, seeing no error, and finding the wrong thing at its URL.
     *
     * Derived from the application's own route table rather than guessed. After
     * adding a public route, check it against this list with
     * `php artisan route:list --except-vendor`.
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = [
        'admin',
        'api',
        'contact',
        'dashboard',
        'dev',
        'dev-login',
        'forgot-password',
        'health',
        'income-planner',
        'livewire',
        'login',
        'logout',
        'media',
        'register',
        'reset-password',
        'robots.txt',
        'settings',
        'sitemap.xml',
        'split-comparison',
        'storage',
        'two-factor-challenge',
        'up',
        'user',
        'verify-email',
        'well-known',
    ];

    /**
     * How long a rendered body stays cached.
     *
     * A TTL rather than forever, and that is load-bearing. The cache key carries
     * updated_at, so every save writes a NEW key and abandons the old one --
     * nothing reads it again and nothing deletes it. The default store here is
     * `database`, and app:prune-expired-storage deletes rows by `expiration`, so
     * a forever entry is a row no code path can reclaim: an edited page would
     * leave one orphan per save in the cache table, permanently. With a TTL the
     * orphan ages out and the scheduled prune collects it.
     */
    private const int BODY_CACHE_TTL_SECONDS = 604800;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Attributes kept out of the audit trail beyond the global denylist.
     *
     * updated_at is noise rather than a change: it moves on every save, so an
     * entry would list it alongside whatever actually changed -- and a
     * cross-second touch() would record it as the ONLY change, a contentless
     * entry in a table nothing may delete from.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['updated_at'];
    }

    /**
     * The attribute that identifies a page in a URL.
     *
     * Load-bearing for link generation, not just for binding. The public route
     * is a fallback (see routes/web.php), whose parameter cannot carry a
     * `{page:slug}` binding hint, so without this `route('pages.show', $page)`
     * would build a URL from the primary key -- /1 rather than /privacy -- and
     * every footer link would point at a page that 404s.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Limit the query to pages visible to the public.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Limit the query to the pages linked in the public footer, in order.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inFooter(Builder $query): void
    {
        $query->published()
            ->where('show_in_footer', true)
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /**
     * Render the page body to HTML.
     *
     * The body is administrator-authored text echoed into the page with `{!! !!}`,
     * so this method is the one thing standing between the database and unescaped
     * output. Both options are load-bearing and must not be removed:
     *
     * - `html_input => 'escape'` renders any HTML in the source as literal text.
     *   Without it a stored `<script>` executes for every visitor, which turns
     *   the admin panel's page editor into stored XSS -- reachable by anyone who
     *   can edit a page, and by anything that can write to the row.
     * - `allow_unsafe_links => false` drops `javascript:` and `data:` URLs, which
     *   are the same attack wearing a Markdown link.
     *
     * Markdown rather than a rich-text editor storing HTML is the decision these
     * options implement: nothing else in this application renders unescaped
     * administrator input, and this feature was not the place to start.
     *
     * Cached on the row's updated_at, so a save rolls the key rather than
     * needing invalidation -- editing a page shows the new copy immediately and
     * the entry the edit orphaned expires on its own -- see
     * BODY_CACHE_TTL_SECONDS for why that TTL is not `forever`.
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
     * The cache key holding this page's rendered body.
     *
     * Keyed on the timestamp as well as the id so any save produces a new key.
     * A page with no updated_at (one built but never stored) falls back to a
     * value that cannot collide with a saved row -- note every UNSAVED
     * instance shares that one key, so a caller rendering previews of several
     * unsaved pages must not cache through this path.
     */
    private function bodyCacheKey(): string
    {
        $version = $this->updated_at?->getTimestamp() ?? 'unsaved';

        return sprintf('page-body:%s:%s', $this->getKey() ?? 'new', $version);
    }
}
