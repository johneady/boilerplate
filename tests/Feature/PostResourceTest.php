<?php

use App\Auth\Role;
use App\Filament\Resources\Posts\Pages\ManagePosts;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Filament\Actions\EditAction;
use Livewire\Livewire;

beforeEach(function (): void {
    app(Settings::class)->set(SettingKey::BlogEnabled, true);

    $this->admin = User::factory()->admin()->create();
});

test('an administrator can reach the posts screen', function () {
    $this->actingAs($this->admin)
        ->get('/admin/posts')
        ->assertSuccessful();
});

test('the create modal does not offer to create another', function () {
    $action = Livewire::actingAs($this->admin)
        ->test(ManagePosts::class)
        ->instance()
        ->getAction('create');

    expect($action->canCreateAnother())->toBeFalse();
});

test('an ordinary user cannot reach the posts screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/posts')
        ->assertForbidden();
});

test('an editor can reach the posts screen', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create())
        ->get('/admin/posts')
        ->assertSuccessful();
});

/**
 * One HTTP-level refusal per neighbouring role: the endpoint performs an
 * authorization check at all. The full permission matrix belongs to the
 * policy, covered in tests/Feature/AuthorizationTest.php.
 */
test('a bookkeeper cannot reach the posts screen', function () {
    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create())
        ->get('/admin/posts')
        ->assertForbidden();
});

test('a guest is sent to the login page', function () {
    $this->get('/admin/posts')->assertRedirect(route('login'));
});

test('the table lists the posts', function () {
    $posts = Post::factory()->count(3)->create();

    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)->assertCanSeeTableRecords($posts);
});

test('the table shows each post\'s state as a word', function () {
    Post::factory()->create(['title' => 'The Draft One']);
    Post::factory()->scheduled()->create(['title' => 'The Scheduled One']);
    Post::factory()->published()->create(['title' => 'The Live One']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->assertSuccessful()
        ->assertSee('Draft')
        ->assertSee('Scheduled')
        ->assertSee('Published');
});

test('an administrator can create a draft post', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->callAction('create', [
            'title' => 'First Post',
            'slug' => 'first-post',
            'body' => '## Hello',
            'published_at' => null,
        ])
        ->assertHasNoActionErrors();

    $post = Post::whereSlug('first-post')->sole();

    // No publish date means a draft, not a zero-date publish.
    expect($post->title)->toBe('First Post')
        ->and($post->published_at)->toBeNull()
        ->and($post->isDraft())->toBeTrue()
        // The author is whoever wrote it -- never chosen in the form, since
        // it decides who may edit the post afterwards.
        ->and($post->author_id)->toBe($this->admin->id);
});

test('a post can be created with a category and tags', function () {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->callAction('create', [
            'title' => 'Categorised',
            'slug' => 'categorised',
            'category_id' => $category->id,
            'tags' => [$tag->id],
        ])
        ->assertHasNoActionErrors();

    $post = Post::whereSlug('categorised')->sole();

    expect($post->category->is($category))->toBeTrue()
        ->and($post->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('every reserved slug is rejected', function (string $slug) {
    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->callAction('create', ['title' => 'Sneaky', 'slug' => $slug])
        ->assertHasActionErrors(['slug']);
})->with(Post::RESERVED_SLUGS);

test('a slug the route cannot match is rejected', function (string $slug) {
    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->callAction('create', ['title' => 'Bad Slug', 'slug' => $slug])
        ->assertHasActionErrors(['slug']);
})->with([
    'uppercase' => 'FirstPost',
    'spaces' => 'first post',
    'underscore' => 'first_post',
    'trailing hyphen' => 'first-',
    'leading hyphen' => '-first',
    'a dot' => 'first.html',
    'a slash' => 'posts/first',
]);

test('a duplicate slug is rejected', function () {
    Post::factory()->create(['slug' => 'taken']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->callAction('create', ['title' => 'Also Here', 'slug' => 'taken'])
        ->assertHasActionErrors(['slug']);
});

/**
 * The record's own slug is exempt, the way ->unique() is -- only a CHANGE to
 * a reserved slug is wrong. See PageResourceTest for the pages version.
 */
test('a post already on a reserved slug can still be edited', function () {
    $post = Post::factory()->create(['slug' => 'feed', 'title' => 'Feed Talk']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->callTableAction(EditAction::class, $post, ['title' => 'Feed Talk, Revised'])
        ->assertHasNoTableActionErrors();

    expect($post->refresh()->title)->toBe('Feed Talk, Revised');
});

test('moving a post onto a different reserved slug is still rejected', function () {
    $post = Post::factory()->create(['slug' => 'feed']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->callTableAction(EditAction::class, $post, ['slug' => 'tag'])
        ->assertHasTableActionErrors(['slug']);

    expect($post->refresh()->slug)->toBe('feed');
});

test('an administrator can delete a post', function () {
    $post = Post::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ManagePosts::class)
        ->callTableAction('delete', $post)
        ->assertHasNoTableActionErrors();

    expect(Post::query()->exists())->toBeFalse();
});

test('an editor may manage only their own posts', function () {
    $editor = User::factory()->role(Role::Editor)->create();
    $own = Post::factory()->for($editor, 'author')->create();
    $others = Post::factory()->create();
    $orphaned = Post::factory()->withoutAuthor()->create();

    expect($editor->can('update', $own))->toBeTrue()
        ->and($editor->can('delete', $own))->toBeTrue()
        ->and($editor->can('update', $others))->toBeFalse()
        ->and($editor->can('delete', $others))->toBeFalse()
        ->and($editor->can('update', $orphaned))->toBeFalse()
        ->and($this->admin->can('update', $others))->toBeTrue()
        ->and($this->admin->can('delete', $orphaned))->toBeTrue();
});

test('a manager may manage any author\'s posts', function () {
    $manager = User::factory()->role(Role::Manager)->create();
    $others = Post::factory()->create();
    $orphaned = Post::factory()->withoutAuthor()->create();

    expect($manager->can('update', $others))->toBeTrue()
        ->and($manager->can('delete', $others))->toBeTrue()
        ->and($manager->can('update', $orphaned))->toBeTrue();
});

test('an editor cannot edit someone else\'s post through the panel', function () {
    $editor = User::factory()->role(Role::Editor)->create();
    $post = Post::factory()->create(['title' => 'Not Yours']);

    $this->actingAs($editor);

    Livewire::test(ManagePosts::class)
        ->assertTableActionHidden(EditAction::class, $post)
        ->assertTableActionHidden('delete', $post)
        ->assertTableActionHidden('cover', $post);
});

test('the visit link is offered only where it would not 404', function () {
    $editor = User::factory()->role(Role::Editor)->create();
    $ownDraft = Post::factory()->for($editor, 'author')->create();
    $othersDraft = Post::factory()->create();
    $othersPublished = Post::factory()->published()->create();

    $this->actingAs($editor);

    Livewire::test(ManagePosts::class)
        ->assertTableActionVisible('visit', $ownDraft)
        ->assertTableActionHidden('visit', $othersDraft)
        ->assertTableActionVisible('visit', $othersPublished);
});

test('an editor\'s bulk delete skips posts written by others', function () {
    $editor = User::factory()->role(Role::Editor)->create();
    $own = Post::factory()->for($editor, 'author')->create();
    $others = Post::factory()->create();

    $this->actingAs($editor);

    Livewire::test(ManagePosts::class)
        ->callTableBulkAction('delete', [$own, $others]);

    expect(Post::query()->pluck('id')->all())->toBe([$others->id]);
});

test('an editor cannot preview someone else\'s draft', function () {
    $editor = User::factory()->role(Role::Editor)->create();
    $draft = Post::factory()->create();

    $this->actingAs($editor)
        ->get(route('blog.show', $draft))
        ->assertNotFound();
});
