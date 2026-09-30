<?php

use App\Blog\BlogManager;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * The blog module's off switch, and everything it hides: the public routes,
 * the navigation link, the home-page teaser and the panel screens.
 *
 * Routes stay REGISTERED while it is off -- a setting cannot decide route
 * registration under the route cache -- and the middleware turns them away
 * at runtime, so an installation with the blog off looks exactly like one
 * without the module.
 */
test('every blog route answers 404 while the blog is switched off', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'the index' => '/blog',
    'a post' => '/blog/hello-world',
    'a category' => '/blog/category/news',
    'a tag' => '/blog/tag/tips',
    'the feed' => '/blog/feed',
]);

test('the blog routes answer 200 once the blog is switched on', function () {
    app(Settings::class)->set(SettingKey::BlogEnabled, true);

    Post::factory()->published()->create(['slug' => 'hello-world']);

    $this->get('/blog')->assertSuccessful();
    $this->get('/blog/hello-world')->assertSuccessful();
    $this->get('/blog/feed')->assertSuccessful();
});

/**
 * The switch is what the header renders on, so the link must disappear with
 * the routes it points at -- the same pairing as the sign-up link.
 */
test('the header blog link follows the switch', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('href="'.route('blog.index').'"', false);

    app(Settings::class)->set(SettingKey::BlogEnabled, true);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('href="'.route('blog.index').'"', false);
});

test('the feed autodiscovery link follows the switch', function () {
    $link = 'type="application/atom+xml"';

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee($link, false);

    app(Settings::class)->set(SettingKey::BlogEnabled, true);

    $this->get('/')
        ->assertSuccessful()
        ->assertSeeInOrder([
            $link,
            'title="'.app(Settings::class)->businessName().' — Blog"',
            'href="'.route('blog.feed').'"',
        ], false);
});

test('the home page teaser follows the switch', function () {
    $post = Post::factory()->published()->create(['title' => 'The Teased Post']);

    // Off: no teaser, even with a published post sitting in the table.
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('From the blog')
        ->assertDontSee('The Teased Post');

    app(Settings::class)->set(SettingKey::BlogEnabled, true);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('From the blog')
        ->assertSee('The Teased Post');

    expect($post->isPublished())->toBeTrue();
})->skip('demo/student: the home page is the café menu, so the blog teaser on welcome.blade.php is not routed.');

test('the home page renders no teaser for an empty blog', function () {
    app(Settings::class)->set(SettingKey::BlogEnabled, true);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('From the blog');
});

/**
 * canAccess() is what hides the whole Content group's blog entries and locks
 * the resource screens -- the same override every payments resource uses.
 * The panel answers 403 rather than the public routes' 404: a panel user
 * authenticated and authorised for the panel is being told the screen is not
 * theirs, not that the application has no such screen.
 */
test('the posts screen is refused while the blog is off', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/posts')
        ->assertForbidden();
});

/**
 * A gate must fail closed. "false" and "off" are both truthy to a plain
 * (bool) cast, so a row hand-edited in a database client would otherwise
 * switch the module on.
 */
test('a stored switch value that is not recognisably true reads as off', function (mixed $stored) {
    Setting::create(['key' => SettingKey::BlogEnabled->value, 'value' => $stored]);

    expect(app(BlogManager::class)->enabled())->toBeFalse();
})->with([
    'the string false' => ['false'],
    'the string off' => ['off'],
    'the string zero' => ['0'],
    'an empty string' => [''],
    'an unrecognised value' => ['garbage'],
    'null' => [null],
]);

/**
 * The routes exist whether or not the module is on, so generating a link
 * never throws -- turning the blog off hides pages, it does not break the
 * ones that link to them.
 */
test('post urls keep building while the blog is off', function () {
    $post = Post::factory()->create(['slug' => 'linked']);

    expect(route('blog.show', $post))->toBe(url('/blog/linked'));
});
