<?php

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator can reach the pages screen', function () {
    $this->actingAs($this->admin)
        ->get('/admin/pages')
        ->assertSuccessful();
});

test('the create modal does not offer to create another', function () {
    $action = Livewire::actingAs($this->admin)
        ->test(ManagePages::class)
        ->instance()
        ->getAction('create');

    expect($action->canCreateAnother())->toBeFalse();
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
        ])
        ->assertHasNoActionErrors();

    $page = Page::whereSlug('cookie-policy')->sole();

    expect($page->title)->toBe('Cookie Policy')
        ->and($page->is_published)->toBeTrue()
        // Footer order is not asked for at creation any more -- it is set by
        // dragging the row. A new page goes to the END of the order, not to
        // the column default of 0, which sorts BEFORE everything and would
        // make every page created here the first link in the public footer.
        ->and($page->sort_order)->toBe(1);
});

test('neither modal offers a footer order field to type into', function () {
    $this->actingAs($this->admin);

    $fields = PageResource::form(new Schema(Livewire::test(ManagePages::class)->instance()))
        ->getFlatFields();

    // sort_order is still IN the schema -- as a Hidden carrying max+1 on
    // create, without which a new page would take the column default of 0 and
    // sort ahead of every existing footer link. What must be gone is the
    // NUMERIC INPUT: the order is dragged, not typed.
    expect($fields)->toHaveKey('sort_order')
        ->and($fields['sort_order'])->toBeInstanceOf(Hidden::class);
});

test('a new page is ordered after the pages already in the footer', function () {
    $this->actingAs($this->admin);

    Page::factory()->published()->create(['title' => 'Alpha', 'sort_order' => 3]);
    Page::factory()->published()->create(['title' => 'Beta', 'sort_order' => 7]);

    Livewire::test(ManagePages::class)
        ->callAction('create', data: [
            'title' => 'Cookie Policy',
            'slug' => 'cookie-policy',
            'body' => '## About cookies',
            'is_published' => true,
            'show_in_footer' => true,
        ])
        ->assertHasNoActionErrors();

    // The column default of 0 would have put the brand-new page FIRST in the
    // public footer, ahead of pages somebody had already ordered by hand.
    expect(Page::whereSlug('cookie-policy')->sole()->sort_order)->toBe(8)
        ->and(Page::inFooter()->pluck('title')->all())
        ->toBe(['Alpha', 'Beta', 'Cookie Policy']);
});

test('dragging rows rewrites the footer order', function () {
    $this->actingAs($this->admin);

    $first = Page::factory()->create(['title' => 'Alpha', 'sort_order' => 10]);
    $second = Page::factory()->create(['title' => 'Beta', 'sort_order' => 20]);

    Livewire::test(ManagePages::class)
        ->call('reorderTable', [$second->getKey(), $first->getKey()]);

    // Dense 1..n over the dragged rows, in the order they were handed over.
    expect($second->refresh()->sort_order)->toBe(1)
        ->and($first->refresh()->sort_order)->toBe(2);
});

test('reordering is refused while a filter hides some of the rows', function () {
    $this->actingAs($this->admin);

    $publishedFirst = Page::factory()->create(['title' => 'Alpha', 'is_published' => true, 'sort_order' => 1]);
    $publishedSecond = Page::factory()->create(['title' => 'Beta', 'is_published' => true, 'sort_order' => 2]);
    $draftFirst = Page::factory()->create(['title' => 'Gamma', 'is_published' => false, 'sort_order' => 3]);
    $draftSecond = Page::factory()->create(['title' => 'Delta', 'is_published' => false, 'sort_order' => 4]);

    // reorderTable() renumbers ONLY the keys it is handed, to a dense 1..n,
    // with no regard for rows the filter hides. Allowed here, dragging the two
    // drafts would set them to 1 and 2 -- the numbers the two published pages
    // already hold -- and the public footer would silently fall back to its
    // title tie-break instead of honouring either drag.
    Livewire::test(ManagePages::class)
        ->set('tableFilters.is_published.value', '0')
        ->call('reorderTable', [$draftSecond->getKey(), $draftFirst->getKey()]);

    expect($publishedFirst->refresh()->sort_order)->toBe(1)
        ->and($publishedSecond->refresh()->sort_order)->toBe(2)
        ->and($draftFirst->refresh()->sort_order)->toBe(3)
        ->and($draftSecond->refresh()->sort_order)->toBe(4);
});

test('the drag handle is withdrawn while a search or filter narrows the table', function () {
    $this->actingAs($this->admin);

    Page::factory()->create(['title' => 'Alpha', 'is_published' => true]);
    Page::factory()->create(['title' => 'Beta', 'is_published' => false]);

    // isReorderable() is what both the handle and reorderTable()'s own guard
    // consult, so asserting it covers the UI and the public Livewire method at
    // once. Searching and filtering are checked separately because either one
    // alone is enough to hide a row from a dense renumbering.
    expect(Livewire::test(ManagePages::class)->instance()->getTable()->isReorderable())->toBeTrue();

    $searched = Livewire::test(ManagePages::class)->set('tableSearch', 'Alpha');

    expect($searched->instance()->getTable()->isReorderable())->toBeFalse();

    $filtered = Livewire::test(ManagePages::class)->set('tableFilters.is_published.value', '0');

    expect($filtered->instance()->getTable()->isReorderable())->toBeFalse();
});

test('the reorder note renders once, below the table', function () {
    $this->actingAs($this->admin);

    // Asserted on the DESCRIPTION text, not the heading: x-filament::callout
    // renders only heading/description/footer and silently drops default-slot
    // content, so a callout with the body missing still looks styled and
    // correct in the browser. See .ai/rules/views-filament.md.
    $note = __('pages.reorder_note.description');

    $html = Livewire::test(ManagePages::class)->html();

    // Below the table only -- it is deliberately not repeated in the header.
    expect(substr_count($html, e($note)))->toBe(1);
});

test('the reorder note explains the footer ordering, not just the drag', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManagePages::class)
        ->assertSee(__('pages.reorder_note.heading'))
        ->assertSee(__('pages.reorder_note.description'));
});

test('the table gates reordering on the update permission', function () {
    $this->actingAs($this->admin);

    $table = Livewire::test(ManagePages::class)->instance()->getTable();

    expect($table->getReorderColumn())->toBe('sort_order')
        ->and($table->isReorderAuthorized())->toBeTrue();
});

test('reordering is refused for someone who may not update pages', function () {
    $this->actingAs($this->admin);

    $table = Livewire::test(ManagePages::class)->instance()->getTable();

    // reorderTable() is a public Livewire method that writes straight to the
    // column, and Filament leaves it AUTHORIZED BY DEFAULT -- it consults no
    // policy of its own, so the table has to ask. Acting as a non-admin is
    // what proves it does: Gate::before answers `true` for an administrator
    // before any policy runs (see AuthServiceProvider), so overriding the
    // policy while signed in as one would prove nothing.
    //
    // No real role reaches this table without UpdatePages today -- App\Auth\Role
    // gives User no permissions at all -- so this gate is the guard for the
    // moment a role sits between the two.
    $this->actingAs(User::factory()->create());

    expect($table->isReorderAuthorized())->toBeFalse();
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
