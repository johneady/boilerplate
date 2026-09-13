<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

test('a published page renders at its slug', function () {
    $page = Page::factory()->published()->create([
        'slug' => 'privacy',
        'title' => 'Privacy Policy',
        'body' => '## How we handle data',
    ]);

    $this->get('/privacy')
        ->assertSuccessful()
        ->assertSee($page->title)
        ->assertSee('How we handle data')
        // The page title prefixes the business name, the pattern partials/head
        // applies to any view passing a title.
        ->assertSee('<title>Privacy Policy - '.e(config('app.name')).'</title>', false);
});

test('a page description overrides the site-wide one', function () {
    Page::factory()->published()->create([
        'slug' => 'terms',
        'seo_description' => 'The terms that apply to this service.',
    ]);

    $this->get('/terms')
        ->assertSuccessful()
        ->assertSee('<meta name="description" content="The terms that apply to this service." />', false);
});

/**
 * A draft must be indistinguishable from a page that was never created, or
 * anybody could enumerate what is being written.
 */
test('an unpublished page is not found for a guest', function () {
    Page::factory()->create(['slug' => 'draft-policy']);

    $this->get('/draft-policy')->assertNotFound();
});

test('an unpublished page is not found for an ordinary user', function () {
    Page::factory()->create(['slug' => 'draft-policy']);

    $this->actingAs(User::factory()->create())
        ->get('/draft-policy')
        ->assertNotFound();
});

/**
 * The only way to see how a page reads before it is live.
 */
test('an unpublished page renders for someone who can edit it', function () {
    $page = Page::factory()->create([
        'slug' => 'draft-policy',
        'title' => 'Draft Policy',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/draft-policy')
        ->assertSuccessful()
        ->assertSee($page->title)
        // Told plainly, so an administrator does not mistake a preview for a
        // live page and stop chasing why nobody can see it.
        ->assertSee('This page is a draft.');
});

test('a slug with no page is not found', function () {
    $this->get('/no-such-page')->assertNotFound();
});

/**
 * The page route is a fallback, and the constraint keeps it off paths with a dot
 * in them. Both are easy to undo by accident, and either would shadow a real
 * route.
 */
test('the page route does not shadow the application routes', function () {
    Page::factory()->published()->create(['slug' => 'about']);

    $this->get('/robots.txt')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $this->get('/sitemap.xml')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml');

    $this->get('/contact')->assertSuccessful()->assertSee('Send message');

    $this->get('/')->assertSuccessful();
});

/**
 * The reason the page route is a fallback rather than an ordinary catch-all.
 *
 * Laravel matches in registration order, so a `Route::get('{slug}')` claims
 * every single-segment path and shadows anything registered after it -- a
 * package's routes, or the runtime-registered routes in ErrorPagesTest, which
 * is where this was caught. A fallback is tried only after everything else
 * fails to match, whenever it was registered.
 */
test('a route registered after the page route still wins', function () {
    Page::factory()->published()->create(['slug' => 'late-route']);

    Route::get('/late-route', fn () => response('the real route', 200))->middleware('web');

    $this->get('/late-route')
        ->assertSuccessful()
        ->assertSee('the real route');
});

/**
 * Links are built from the slug, not the primary key. The fallback route's
 * parameter cannot carry a {page:slug} binding hint, so this depends on
 * Page::getRouteKeyName() -- without it every footer link points at /1.
 */
test('a page url is built from its slug', function () {
    $page = Page::factory()->published()->create(['slug' => 'privacy']);

    expect(route('pages.show', $page))->toBe(url('/privacy'));
});

/**
 * The security property the Markdown decision exists to provide. The body is
 * administrator-authored text echoed with {!! !!}, so stored HTML must reach the
 * browser as text -- see Page::renderedBody().
 */
test('html in a page body is escaped rather than rendered', function () {
    Page::factory()->published()->create([
        'slug' => 'about',
        'body' => 'Hello <script>alert("xss")</script> world',
    ]);

    $response = $this->get('/about')->assertSuccessful();

    expect((string) $response->getContent())
        ->not->toContain('<script>alert("xss")</script>')
        ->toContain('&lt;script&gt;');
});

test('a javascript link in a page body is stripped', function () {
    Page::factory()->published()->create([
        'slug' => 'about',
        'body' => '[click me](javascript:alert(1))',
    ]);

    $content = (string) $this->get('/about')->assertSuccessful()->getContent();

    expect($content)
        ->not->toContain('javascript:alert')
        ->toContain('click me');
});

test('markdown in a page body renders as html', function () {
    Page::factory()->published()->create([
        'slug' => 'about',
        'body' => "## A heading\n\n- first\n- second\n\n**bold** and [a link](https://example.com)",
    ]);

    $content = (string) $this->get('/about')->assertSuccessful()->getContent();

    expect($content)
        ->toContain('<h2>A heading</h2>')
        ->toContain('<li>first</li>')
        ->toContain('<strong>bold</strong>')
        ->toContain('<a href="https://example.com">a link</a>');
});

test('a page with no body renders without error', function () {
    Page::factory()->published()->create(['slug' => 'about', 'body' => null]);

    $this->get('/about')->assertSuccessful();
});

/**
 * The cache key is the row's updated_at, so a save must roll it. Without that an
 * edit would appear saved in the panel and unchanged on the public page.
 */
test('editing a page body updates the rendered output', function () {
    $page = Page::factory()->published()->create([
        'slug' => 'about',
        'body' => 'The original text.',
    ]);

    $this->get('/about')->assertSee('The original text.');

    // Travelled forward because the key is keyed on the timestamp, and a save
    // within the same second would reuse it.
    $this->travel(1)->second();

    $page->update(['body' => 'The replacement text.']);

    $this->get('/about')
        ->assertSee('The replacement text.')
        ->assertDontSee('The original text.');
});

/**
 * The cache key carries updated_at, so every save abandons the previous entry.
 * Written with rememberForever those orphans were unreclaimable: the default
 * store is `database` and app:prune-expired-storage deletes rows by
 * `expiration`, so an edited page leaked one permanent cache row per save. The
 * TTL is what lets the prune collect them.
 */
test('a rendered body is cached with an expiry rather than forever', function () {
    $page = Page::factory()->published()->create(['slug' => 'about', 'body' => 'The original text.']);

    // Spied rather than read back from the store: the test suite runs on the
    // `array` driver, which keeps no expiration column to inspect.
    Cache::shouldReceive('remember')
        ->once()
        ->withArgs(fn (string $key, mixed $ttl, Closure $callback): bool => str_starts_with($key, 'page-body:')
            && is_int($ttl)
            && $ttl > 0)
        ->andReturn('<p>The original text.</p>');

    Cache::shouldReceive('rememberForever')->never();

    expect($page->renderedBody())->toBe('<p>The original text.</p>');
});

test('the footer links to the published pages in order', function () {
    Page::factory()->published()->create(['slug' => 'terms', 'title' => 'Terms', 'sort_order' => 20]);
    Page::factory()->published()->create(['slug' => 'privacy', 'title' => 'Privacy', 'sort_order' => 10]);

    $content = (string) $this->get('/')->assertSuccessful()->getContent();

    expect($content)
        ->toContain('href="'.route('pages.show', 'privacy').'"')
        ->toContain('href="'.route('pages.show', 'terms').'"')
        ->and(strpos($content, 'Privacy'))->toBeLessThan(strpos($content, 'Terms'));
});

test('the footer omits unpublished pages and those hidden from it', function () {
    Page::factory()->create(['slug' => 'draft-page', 'title' => 'Draft Page']);
    Page::factory()->published()->hiddenFromFooter()->create([
        'slug' => 'hidden-page',
        'title' => 'Hidden Page',
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('Draft Page')
        ->assertDontSee('Hidden Page');
});

/**
 * The auth pages render the same footer with compact, and $footerPages is a
 * closure that the compact branch never invokes. If that ever changes, the
 * login page starts carrying a row of policy links.
 */
test('the compact footer on the auth pages has no page links', function () {
    Page::factory()->published()->create(['slug' => 'privacy', 'title' => 'Privacy Policy']);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertDontSee('Privacy Policy');
});

/**
 * The seeded 'contact' page and the named contact route resolve to the same URL,
 * so both sources offer it. A sitemap listing one URL twice is malformed.
 */
test('a page sharing a url with a named route is listed once', function () {
    Page::factory()->published()->create(['slug' => 'contact', 'title' => 'Contact']);

    $xml = simplexml_load_string(
        (string) $this->get('/sitemap.xml')->assertSuccessful()->getContent(),
    );

    $locations = array_map(
        fn (SimpleXMLElement $url): string => (string) $url->loc,
        iterator_to_array($xml->url, false),
    );

    expect($locations)->toEqual(array_unique($locations))
        ->and(array_count_values($locations)[route('contact')])->toBe(1);
});

test('the published pages appear in the sitemap', function () {
    Page::factory()->published()->create(['slug' => 'privacy']);
    Page::factory()->create(['slug' => 'draft-policy']);

    $content = (string) $this->get('/sitemap.xml')->assertSuccessful()->getContent();

    expect($content)
        ->toContain('<loc>'.route('pages.show', 'privacy').'</loc>')
        // A draft 404s, and a sitemap must not point a crawler at a 404.
        ->not->toContain('draft-policy');
});
