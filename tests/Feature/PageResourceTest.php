<?php

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator can reach the pages screen', function () {
    $this->actingAs($this->admin)
        ->get('/admin/pages')
        ->assertSuccessful();
});

test('an ordinary user cannot reach the pages screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/pages')
        ->assertForbidden();
});

test('a guest is sent to the login page', function () {
    $this->get('/admin/pages')->assertRedirect(route('login'));
});

test('the table lists the pages', function () {
    $pages = Page::factory()->count(3)->create();

    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)->assertCanSeeTableRecords($pages);
});

test('an administrator can create a page', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->callAction('create', [
            'title' => 'Cookie Policy',
            'slug' => 'cookie-policy',
            'body' => '## About cookies',
            'is_published' => true,
            'show_in_footer' => true,
            'sort_order' => 5,
        ])
        ->assertHasNoActionErrors();

    $page = Page::whereSlug('cookie-policy')->sole();

    expect($page->title)->toBe('Cookie Policy')
        ->and($page->is_published)->toBeTrue()
        ->and($page->sort_order)->toBe(5);
});

test('an administrator can edit a page', function () {
    $page = Page::factory()->create(['title' => 'Old Title']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->callTableAction(EditAction::class, $page, ['title' => 'New Title'])
        ->assertHasNoTableActionErrors();

    expect($page->refresh()->title)->toBe('New Title');
});

test('an administrator can delete a page', function () {
    $page = Page::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)->callTableAction(DeleteAction::class, $page);

    expect(Page::count())->toBe(0);
});

/**
 * The catch-all route only sees paths nothing else matched, so a page slugged
 * "login" would save cleanly and then be permanently unreachable. Rejecting it
 * at the form is the only place an administrator finds out.
 */
test('a reserved slug is rejected', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->callAction('create', [
            'title' => 'Sneaky',
            'slug' => 'login',
            'is_published' => true,
        ])
        ->assertHasActionErrors(['slug']);

    expect(Page::count())->toBe(0);
});

test('every reserved slug is rejected', function (string $slug) {
    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->callAction('create', ['title' => 'Sneaky', 'slug' => $slug])
        ->assertHasActionErrors(['slug']);
})->with(Page::RESERVED_SLUGS);

/**
 * The slug has to match the route constraint, or the page 404s after saving.
 */
test('a slug the route cannot match is rejected', function (string $slug) {
    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->callAction('create', ['title' => 'Bad Slug', 'slug' => $slug])
        ->assertHasActionErrors(['slug']);
})->with([
    'uppercase' => 'Privacy',
    'spaces' => 'privacy policy',
    'underscore' => 'privacy_policy',
    'trailing hyphen' => 'privacy-',
    'leading hyphen' => '-privacy',
    'a dot' => 'privacy.html',
    'a slash' => 'legal/privacy',
]);

/**
 * PagesSeeder ships a 'contact' page on purpose -- that row supplies the copy
 * above the contact form -- and 'contact' is a reserved slug. A flat notIn()
 * therefore rejected the page's OWN unchanged slug, making the seeded row
 * permanently unsaveable: an administrator editing only its title was refused on
 * a field they never touched. Only a CHANGE to a reserved slug is wrong.
 */
test('a page already on a reserved slug can still be edited', function () {
    $page = Page::factory()->published()->create(['slug' => 'contact', 'title' => 'Contact']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->callTableAction(EditAction::class, $page, ['title' => 'Talk To Us'])
        ->assertHasNoTableActionErrors();

    expect($page->refresh()->title)->toBe('Talk To Us');
});

test('moving a page onto a different reserved slug is still rejected', function () {
    $page = Page::factory()->create(['slug' => 'contact']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->callTableAction(EditAction::class, $page, ['slug' => 'login'])
        ->assertHasTableActionErrors(['slug']);

    expect($page->refresh()->slug)->toBe('contact');
});

test('a duplicate slug is rejected', function () {
    Page::factory()->create(['slug' => 'privacy']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->callAction('create', ['title' => 'Another Privacy', 'slug' => 'privacy'])
        ->assertHasActionErrors(['slug']);

    expect(Page::count())->toBe(1);
});

test('the resource sits in the content navigation group', function () {
    expect(PageResource::getNavigationGroup())->toBe('Content');
});

/**
 * Clicking a row must open the edit modal, not leave the panel.
 *
 * ListRecords, which ManageRecords extends, makes the whole row a link to the
 * first action named 'view' or 'edit' that has a URL. The "open the live page"
 * action was named 'view' and had one, so every row click used to navigate to
 * the public page instead of editing it.
 */
test('clicking a row opens the edit modal rather than the public page', function () {
    $page = Page::factory()->create(['slug' => 'privacy']);

    $this->actingAs($this->admin);

    $table = Livewire::test(ManagePages::class)->instance()->getTable();

    expect($table->getRecordAction($page))->toBe('edit')
        ->and($table->getRecordUrl($page))->toBeNull();
});

/**
 * The live-page link is still there, just not as the row's own click target.
 */
test('the visit action links to the public page in a new tab', function () {
    $page = Page::factory()->published()->create(['slug' => 'privacy']);

    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->assertTableActionExists('visit')
        ->assertTableActionHasUrl('visit', route('pages.show', $page), record: $page);
});
