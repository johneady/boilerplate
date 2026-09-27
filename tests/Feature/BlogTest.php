<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;

beforeEach(function (): void {
    app(Settings::class)->set(SettingKey::BlogEnabled, true);
});

test('a published post renders at its slug', function () {
    $author = User::factory()->create(['name' => 'Priya Chen']);

    $post = Post::factory()->for($author, 'author')->published()->create([
        'slug' => 'hello-world',
        'title' => 'Hello World',
        'body' => '## The first post',
    ]);

    $this->get('/blog/hello-world')
        ->assertSuccessful()
        ->assertSee($post->title)
        ->assertSee('The first post')
        ->assertSee('Priya Chen')
        ->assertSee('<title>Hello World - '.e(config('app.name')).'</title>', false);
});

test('a post description overrides the site-wide one', function () {
    Post::factory()->published()->create([
        'slug' => 'described',
        'seo_description' => 'Everything worth knowing about this post.',
    ]);

    $this->get('/blog/described')
        ->assertSuccessful()
        ->assertSee('<meta name="description" content="Everything worth knowing about this post." />', false);
});

test('a post with no author is credited to the business', function () {
    $businessName = app(Settings::class)->businessName();

    Post::factory()->published()->withoutAuthor()->create(['slug' => 'anonymous']);

    $this->get('/blog/anonymous')
        ->assertSuccessful()
        ->assertSee('By '.$businessName);
});

/**
 * A draft or a scheduled post must be indistinguishable from one that was
 * never created, or anybody could enumerate what is being written and when.
 */
test('a draft post is not found for a guest', function () {
    Post::factory()->create(['slug' => 'unwritten']);

    $this->get('/blog/unwritten')->assertNotFound();
});

test('a scheduled post is not found for a guest', function () {
    Post::factory()->scheduled()->create(['slug' => 'forthcoming']);

    $this->get('/blog/forthcoming')->assertNotFound();
});

test('a draft post is not found for an ordinary user', function () {
    Post::factory()->create(['slug' => 'unwritten']);

    $this->actingAs(User::factory()->create())
        ->get('/blog/unwritten')
        ->assertNotFound();
});

/**
 * The only way to see how a post reads before it is live -- the panel's
 * "visit" action relies on it.
 */
test('a draft post renders for someone who can edit it', function () {
    $post = Post::factory()->create(['slug' => 'unwritten', 'title' => 'The Unwritten Post']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/blog/unwritten')
        ->assertSuccessful()
        ->assertSee($post->title)
        ->assertSee('This post is a draft.');
});

test('a scheduled post renders for someone who can edit it', function () {
    $post = Post::factory()->scheduled()->create(['slug' => 'forthcoming']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/blog/forthcoming')
        ->assertSuccessful()
        // Told plainly, so an editor does not mistake a preview for a live
        // post and share the link early.
        ->assertSee('This post is scheduled.');
});

test('a scheduled post goes live when its date arrives', function () {
    Post::factory()->scheduled()->create(['slug' => 'forthcoming']);

    $this->get('/blog/forthcoming')->assertNotFound();

    // No scheduler moves it: the published scope compares against now(), so
    // travelling past the date is the whole publishing mechanism.
    $this->travel(2)->days();

    $this->get('/blog/forthcoming')->assertSuccessful();
});

test('a slug with no post is not found', function () {
    $this->get('/blog/no-such-post')->assertNotFound();
});

test('the blog index lists only the published posts, newest first', function () {
    Post::factory()->published()->create(['title' => 'The Oldest', 'published_at' => now()->subDays(10)]);
    Post::factory()->published()->create(['title' => 'The Newest', 'published_at' => now()->subDays(1)]);
    Post::factory()->create(['title' => 'The Draft']);
    Post::factory()->scheduled()->create(['title' => 'The Scheduled']);

    $content = (string) $this->get('/blog')->assertSuccessful()->getContent();

    expect($content)
        ->toContain('The Newest')
        ->toContain('The Oldest')
        ->not->toContain('The Draft')
        ->not->toContain('The Scheduled')
        // Newest first: the freshest post is what a visitor came for.
        ->and(strpos($content, 'The Newest'))->toBeLessThan(strpos($content, 'The Oldest'));
});

test('the index omits categories with nothing published', function () {
    Category::factory()->create(['name' => 'Empty Category', 'slug' => 'empty']);
    $full = Category::factory()->create(['name' => 'Full Category', 'slug' => 'full']);
    Post::factory()->published()->for($full, 'category')->create();

    $this->get('/blog')
        ->assertSuccessful()
        ->assertSee('Full Category')
        ->assertDontSee('Empty Category');
});

test('a category archive lists only that category\'s published posts', function () {
    $category = Category::factory()->create(['slug' => 'guides']);
    Post::factory()->published()->for($category, 'category')->create(['title' => 'In The Category']);
    Post::factory()->published()->create(['title' => 'Outside The Category']);
    Post::factory()->for($category, 'category')->create(['title' => 'Unpublished In The Category']);

    $this->get('/blog/category/guides')
        ->assertSuccessful()
        ->assertSee('In The Category')
        ->assertDontSee('Outside The Category')
        ->assertDontSee('Unpublished In The Category');
});

test('a tag archive lists the posts carrying the tag', function () {
    $tag = Tag::factory()->create(['slug' => 'tips']);
    Post::factory()->published()->hasAttached($tag)->create(['title' => 'Tagged Post']);
    Post::factory()->published()->create(['title' => 'Untagged Post']);

    $this->get('/blog/tag/tips')
        ->assertSuccessful()
        ->assertSee('Tagged Post')
        ->assertDontSee('Untagged Post');
});

test('a slug the post route cannot match is not found', function () {
    $this->get('/blog/Privacy.Policy')->assertNotFound();
});

/**
 * /blog/feed and the archive prefixes sit on the same depth as a post, so
 * route order is what keeps them reachable. Post::RESERVED_SLUGS stops a
 * post claiming the segment; this pins that the routes win regardless.
 */
test('the feed and archive routes are not shadowed by a post route', function () {
    Category::factory()->create(['slug' => 'news']);
    Tag::factory()->create(['slug' => 'news']);
    Post::factory()->published()->create(['slug' => 'some-post']);

    $this->get('/blog/feed')->assertSuccessful();
    $this->get('/blog/category/news')->assertSuccessful();
    $this->get('/blog/tag/news')->assertSuccessful();
});

test('the feed serves atom with only the published posts', function () {
    Post::factory()->published()->create(['title' => 'Feedworthy', 'published_at' => now()->subDay()]);
    Post::factory()->create(['title' => 'Unfed Draft']);
    Post::factory()->scheduled()->create(['title' => 'Unfed Scheduled']);

    $response = $this->get('/blog/feed')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/atom+xml; charset=utf-8');

    $xml = simplexml_load_string((string) $response->getContent());

    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe('feed');

    $titles = array_map(
        fn (SimpleXMLElement $entry): string => (string) $entry->title,
        iterator_to_array($xml->entry, false),
    );

    expect($titles)->toContain('Feedworthy')
        ->not->toContain('Unfed Draft')
        ->not->toContain('Unfed Scheduled');
});

/**
 * The newest-published entry is not always the latest change: an older post
 * edited today moves the feed, and a post saved while scheduled must not read
 * as updated before it was published.
 */
test('the feed updated timestamps follow the latest change', function () {
    $this->travelTo(now()->subDays(3));
    $wasScheduled = Post::factory()->create(['published_at' => now()->addDay()]);
    $this->travelBack();

    $older = Post::factory()->published()->create(['published_at' => now()->subDays(10)]);
    $older->forceFill(['updated_at' => now()->subMinute()])->save();

    $xml = simplexml_load_string((string) $this->get('/blog/feed')->getContent());

    $updatedBySlug = [];

    foreach ($xml->entry as $entry) {
        $updatedBySlug[basename((string) $entry->id)] = (string) $entry->updated;
    }

    expect((string) $xml->updated)->toBe($older->fresh()->updated_at->toRfc3339String())
        ->and($updatedBySlug[$wasScheduled->slug])->toBe($wasScheduled->published_at->toRfc3339String());
});

/**
 * The security property the Markdown decision exists to provide -- see
 * PageTest for the same assertions on content pages.
 */
test('html in a post body is escaped rather than rendered', function () {
    Post::factory()->published()->create([
        'slug' => 'about-this',
        'body' => 'Hello <script>alert("xss")</script> world',
    ]);

    $content = (string) $this->get('/blog/about-this')->assertSuccessful()->getContent();

    expect($content)
        ->not->toContain('<script>alert("xss")</script>')
        ->toContain('&lt;script&gt;');
});

test('a javascript link in a post body is stripped', function () {
    Post::factory()->published()->create([
        'slug' => 'about-this',
        'body' => '[click me](javascript:alert(1))',
    ]);

    $content = (string) $this->get('/blog/about-this')->assertSuccessful()->getContent();

    expect($content)
        ->not->toContain('javascript:alert')
        ->toContain('click me');
});

test('markdown in a post body renders as html', function () {
    Post::factory()->published()->create([
        'slug' => 'about-this',
        'body' => "## A heading\n\n- first\n- second\n\n**bold** and [a link](https://example.com)",
    ]);

    $content = (string) $this->get('/blog/about-this')->assertSuccessful()->getContent();

    expect($content)
        ->toContain('<h2>A heading</h2>')
        ->toContain('<li>first</li>')
        ->toContain('<strong>bold</strong>')
        ->toContain('<a href="https://example.com">a link</a>');
});

test('a post url is built from its slug', function () {
    $post = Post::factory()->published()->create(['slug' => 'hello-world']);

    expect(route('blog.show', $post))->toBe(url('/blog/hello-world'));
});

test('a post links to its neighbours in publishing order', function () {
    Post::factory()->published()->create(['slug' => 'first', 'published_at' => now()->subDays(2)]);
    Post::factory()->published()->create(['slug' => 'middle', 'published_at' => now()->subDay()]);
    Post::factory()->published()->create(['slug' => 'last', 'published_at' => now()]);

    // A draft must never become a neighbour, whatever its row would sort as.
    Post::factory()->create(['slug' => 'interloper']);

    $content = (string) $this->get('/blog/middle')->assertSuccessful()->getContent();

    // The head's canonical link aside (every page names itself there), the
    // neighbours are the posts around this one and no draft.
    $body = (string) str($content)->after('</head>');

    expect($body)
        ->toContain('href="'.route('blog.show', 'first').'"')
        ->toContain('href="'.route('blog.show', 'last').'"')
        ->not->toContain('href="'.route('blog.show', 'interloper').'"');
});

test('posts sharing a publish date are still each other\'s neighbours', function () {
    $publishedAt = now()->subDay()->startOfMinute();

    $first = Post::factory()->published()->create(['published_at' => $publishedAt]);
    $second = Post::factory()->published()->create(['published_at' => $publishedAt]);

    expect($first->nextPost()?->is($second))->toBeTrue()
        ->and($second->previousPost()?->is($first))->toBeTrue();
});

test('the excerpt drawn from a post body is plain text, not markdown', function () {
    $post = Post::factory()->published()->create([
        'seo_description' => null,
        'body' => "## Opening heading\n\nSome **bold** words & more.",
    ]);

    expect($post->excerpt())->toBe('Opening heading Some bold words & more.');
});

test('the published posts appear in the sitemap', function () {
    Post::factory()->published()->create(['slug' => 'mapped']);
    Post::factory()->create(['slug' => 'unmapped-draft']);
    Post::factory()->scheduled()->create(['slug' => 'unmapped-scheduled']);

    $content = (string) $this->get('/sitemap.xml')->assertSuccessful()->getContent();

    expect($content)
        ->toContain('<loc>'.route('blog.index').'</loc>')
        ->toContain('<loc>'.route('blog.show', 'mapped').'</loc>')
        // A draft 404s, and a sitemap must not point a crawler at a 404.
        ->not->toContain('unmapped-draft')
        ->not->toContain('unmapped-scheduled');
});
