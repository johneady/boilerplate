<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\RendersMarkdownBody;
use App\Media\HoldsMedia;
use Carbon\CarbonImmutable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    use Auditable, HasFactory, HasMedia, RendersMarkdownBody;

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
        'blog',
        'contact',
        'dashboard',
        'dev',
        'dev-login',
        'forgot-password',
        'health',
        'livewire',
        'login',
        'logout',
        'media',
        'perfume',
        'perfumes',
        'pricing',
        'register',
        'reset-password',
        'robots.txt',
        'settings',
        'sitemap.xml',
        'stats',
        'storage',
        'two-factor-challenge',
        'up',
        'user',
        'verify-email',
        'well-known',
    ];

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

    protected function bodyCacheKeyPrefix(): string
    {
        return 'page-body';
    }
}
