<?php

use App\Auth\Role;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Media\MediaCollection;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * The configured body editor field, as the Pages form builds it.
 */
function bodyField(): MarkdownEditor
{
    $body = collect(PageResource::form(new Schema(new ManagePages))->getComponents())
        ->first(fn ($component): bool => method_exists($component, 'getName')
            && $component->getName() === 'body');

    expect($body)->toBeInstanceOf(MarkdownEditor::class);

    return $body;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    actingAs(User::factory()->create(['role' => Role::Admin]));
});

/*
 * Body images are uploaded through the editor's own attachment button and
 * deliberately skip the media library: adding an image while writing is worth
 * more here than the bookkeeping.
 *
 * That means NOTHING re-encodes them -- they are served exactly as uploaded,
 * EXIF included -- and no media row describes them, so the orphan prune cannot
 * collect one after the body stops referencing it. The settings below are the
 * only controls on this path, which is why each has a test: a regression here
 * is silent.
 */
test('body attachments are confined to their own directory', function () {
    // Without a directory they land loose at the public disk root, where they
    // are indistinguishable from anything else and impossible to find later.
    expect(bodyField()->getFileAttachmentsDirectory())->toBe('page-body');
});

test('body attachments refuse svg', function () {
    // An SVG is a scriptable document served from this application's own
    // origin. There is no re-encoding on this path to fall back on, so the
    // accepted-type list is the only thing keeping one out.
    expect(bodyField()->getFileAttachmentsAcceptedFileTypes())
        ->not->toContain('image/svg+xml')
        ->toContain('image/png');
});

test('body attachments are size capped', function () {
    expect(bodyField()->getFileAttachmentsMaxSize())
        ->toBe((int) config('images.max_kilobytes'));
});

test('the editor still offers its upload button', function () {
    // fileAttachments(false) would strip this, and with it the only way to add
    // an image while writing. Keeping it is a deliberate choice.
    expect(bodyField()->hasFileAttachments())->toBeTrue()
        ->and(bodyField()->getToolbarButtons())->toContain(['table', 'attachFiles']);
});

test('the pages table offers no separate image action', function () {
    $page = Page::factory()->create();

    // Images are added in the editor. A second button on the table was removed
    // rather than left as a competing way in.
    Livewire::test(ManagePages::class)
        ->assertTableActionDoesNotExist('uploadImage', record: $page);
});

test('a page still owns any media attached to it in code', function () {
    // The collection and the cascade stay, even though no panel screen writes
    // to them -- a project building on this boilerplate attaches page media
    // through MediaManager.
    $page = Page::factory()->create();

    Media::factory()->create([
        'model_type' => $page->getMorphClass(),
        'model_id' => $page->getKey(),
        'collection' => MediaCollection::PageImage->value,
    ]);

    $page->delete();

    expect(Media::query()->count())->toBe(0);
});

test('clicking a page row opens the edit modal', function () {
    $page = Page::factory()->create();

    // recordAction('edit') with recordUrl(null) is what makes the whole row
    // open the modal rather than navigate. A column with copyable() on it
    // intercepts the click for its own copy behaviour and swallows this, which
    // is why the slug column deliberately does not have it.
    $table = PageResource::table(
        new Table(new ManagePages),
    );

    expect($table->getRecordAction($page))->toBe('edit')
        ->and($table->getRecordUrl($page))->toBeNull();

    $slug = collect($table->getColumns())->first(
        fn ($column): bool => $column->getName() === 'slug',
    );

    expect($slug->isCopyable($page))->toBeFalse();
});
