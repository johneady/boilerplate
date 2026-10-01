<?php

use App\Blog\BlogManager;
use App\Media\MediaCollection;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Database\Seeders\BlogSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

test('it seeds the sample content', function () {
    // The accounts its authors are drawn from: DatabaseSeeder creates these
    // before the blog is seeded, so the test does the same.
    User::factory()->create(['email' => 'admin@example.com']);
    User::factory()->create(['email' => 'editor@example.com']);
    User::factory()->create(['email' => 'manager@example.com']);

    $this->seed(BlogSeeder::class);

    $posts = Post::query()->get();

    expect($posts)->not->toBeEmpty()
        ->and($posts->whereNotNull('published_at')->count())->toBeGreaterThan(0)
        // The editorial mix: at least one scheduled post and one draft, so
        // the panel's status column has something to show on every state.
        ->and($posts->filter(fn (Post $post): bool => $post->isScheduled())->count())->toBe(1)
        ->and($posts->filter(fn (Post $post): bool => $post->isDraft())->count())->toBe(1)
        ->and(Category::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(Tag::query()->count())->toBeGreaterThanOrEqual(8)
        // Every post is credited to one of the seeded accounts, so the
        // bylines vary across the demo.
        ->and($posts->reject(fn (Post $post): bool => $post->author_id !== null))->toBeEmpty()
        ->and($posts->pluck('author_id')->unique()->count())->toBeGreaterThan(1);
});

test('its newest published post is a recent one', function () {
    $this->seed(BlogSeeder::class);

    $newest = Post::query()
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now())
        ->orderByDesc('published_at')
        ->first();

    // The freshest seeded post went live ten days ago -- recent enough to
    // sit atop the index, old enough that the demo is not "posted today".
    expect($newest->published_at->lessThan(now()))->toBeTrue()
        ->and($newest->published_at->greaterThan(now()->subDays(11)))->toBeTrue();
});

test('it leaves an installation that has posts alone', function () {
    $this->seed(BlogSeeder::class);

    $count = Post::query()->count();

    $post = Post::query()->first();
    $post->update(['title' => 'An Edited Title']);

    $this->seed(BlogSeeder::class);

    // A re-seed neither duplicates nor resets: redeploying must be a no-op.
    expect(Post::query()->count())->toBe($count)
        ->and($post->fresh()->title)->toBe('An Edited Title');
});

/**
 * The same stance DemoBusinessSeeder takes for payments: the demo instance
 * gets a working blog the moment it comes up, but a saved choice -- including
 * an explicit "off" -- is never overwritten. The environment is spoken for
 * directly because the suite itself runs in 'testing', where the seeder
 * deliberately leaves the switch alone.
 */
test('it switches the blog on for a demo instance', function () {
    app()->detectEnvironment(fn () => 'local');

    $seeder = new BlogSeeder;
    $seeder->withCovers = false;

    try {
        $seeder->run();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(app(BlogManager::class)->enabled())->toBeTrue();
});

test('it never overwrites an operator\'s blog switch', function () {
    app(Settings::class)->set(SettingKey::BlogEnabled, false);

    app()->detectEnvironment(fn () => 'local');

    $seeder = new BlogSeeder;
    $seeder->withCovers = false;

    try {
        $seeder->run();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(app(BlogManager::class)->enabled())->toBeFalse();
});

test('it never runs in production', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        (new BlogSeeder)->run();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(Post::query()->exists())->toBeFalse();
});

test('it seeds no cover images in the test environment', function () {
    $this->seed(BlogSeeder::class);

    // Covers cost real image decoding; the suite gets the rows without them
    // unless a test asks for them deliberately.
    expect(Post::query()->get()->filter(fn (Post $post): bool => $post->hasMedia(MediaCollection::PostCover)))->toBeEmpty();
});

/**
 * The full path a demo cover takes: generated with Intervention, attached
 * through MediaManager, re-encoded by the processing job (sync in the suite)
 * and served back as a URL -- the same pipeline an uploaded cover follows.
 */
test('it attaches generated covers through the media pipeline', function () {
    // The pipeline stages on the private disk and publishes on the public
    // one; unfaked, every run left its covers in the real storage tree.
    Storage::fake('local');
    Storage::fake('public');

    app()->detectEnvironment(fn () => 'local');

    $seeder = new BlogSeeder;

    try {
        $seeder->run();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    $posts = Post::query()->get();

    expect($posts)->not->toBeEmpty();

    foreach ($posts as $post) {
        expect($post->coverUrl('card'))->not->toBeNull()
            ->and($post->coverUrl('wide'))->not->toBeNull();
    }
});

test('the seeder uses no factory, which production has no faker for', function () {
    expect(file_get_contents(base_path('database/seeders/BlogSeeder.php')))
        ->not->toContain('factory(');
});

/**
 * The demo accounts are the seeder's author pool, and they exist because
 * DatabaseSeeder seeds them before this runs -- the admin comes from
 * config('first.user'). Asserted on a seed of the whole database so the
 * ordering holds, not just the seeder in isolation.
 */
test('its authors are accounts the database seeder creates', function () {
    // The travel demo's DatabaseSeeder no longer seeds the blog itself, so
    // the blog seeder runs after it here, in the order it used to.
    $this->seed(DatabaseSeeder::class);
    $this->seed(BlogSeeder::class);

    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue()
        ->and(Post::query()->exists())->toBeTrue()
        ->and(Post::query()->whereNull('author_id')->exists())->toBeFalse()
        ->and(Post::query()->distinct()->count('author_id'))->toBeGreaterThan(1);
});
