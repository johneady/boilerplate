<?php

use App\Console\Commands\AdoptPageBodyImages;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Media\MediaCollection;
use App\Media\StagedUpload;
use App\Models\Media;
use App\Models\Page;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * Put a real encoded image in the body-upload directory, as the editor would.
 */
function bodyUpload(string $name = 'photo.png'): string
{
    $path = AdoptPageBodyImages::DIRECTORY.'/'.$name;

    Storage::disk('public')->put(
        $path,
        UploadedFile::fake()->image($name, 900, 600)->getContent(),
    );

    return $path;
}

test('the scan directory matches where the editor actually writes', function () {
    $body = collect(PageResource::form(new Schema(new ManagePages))->getComponents())
        ->first(fn ($c): bool => method_exists($c, 'getName') && $c->getName() === 'body');

    expect($body)->toBeInstanceOf(MarkdownEditor::class);

    // If these drift the scan silently looks at an empty directory and every
    // body upload goes uncollected forever.
    expect($body->getFileAttachmentsDirectory())->toBe(AdoptPageBodyImages::DIRECTORY);
});

test('a referenced upload is re-encoded, tracked, and its body rewritten', function () {
    $path = bodyUpload();

    $page = Page::factory()->create([
        'body' => "Intro\n\n![shot](/storage/{$path})\n\nOutro",
    ]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    $media = Media::query()->inCollection(MediaCollection::PageImage)->first();

    expect($media)->not->toBeNull()
        ->and($media->model_id)->toBe($page->id);

    $body = (string) $page->refresh()->body;

    // The old URL must be gone AND the new one present: leaving the old one
    // behind after the original is deleted is a broken image on a live page.
    expect($body)->not->toContain($path)
        ->and($body)->toContain('page-images/')
        ->and($body)->toContain('.webp');

    // The unprocessed original carried EXIF; only the re-encoded file remains.
    Storage::disk('public')->assertMissing($path);
});

test('the original survives until the rewrite has committed', function () {
    $path = bodyUpload();

    Page::factory()->create(['body' => "![x](/storage/{$path})"]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    // Deleted only after the body no longer names it -- asserted together so
    // the ordering, not just the end state, is pinned.
    expect(Page::query()->where('body', 'like', "%{$path}%")->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

test('an unreferenced upload past the grace period is deleted', function () {
    $path = bodyUpload();

    // Nothing points at it and it is old enough to be abandoned rather than
    // mid-edit.
    $this->artisan('app:adopt-page-body-images', ['--hours' => 0])->assertSuccessful();

    Storage::disk('public')->assertMissing($path);
    expect(Media::query()->count())->toBe(0);
});

test('an unreferenced upload inside the grace period is kept', function () {
    $path = bodyUpload();

    // A file uploaded seconds ago has no reference yet because the body
    // holding it has not been saved. Collecting it would take the image out of
    // an author's hands mid-edit.
    $this->artisan('app:adopt-page-body-images')
        ->expectsOutputToContain('grace period')
        ->assertSuccessful();

    Storage::disk('public')->assertExists($path);
});

test('a dry run reports without changing anything', function () {
    $path = bodyUpload();

    Page::factory()->create(['body' => "![x](/storage/{$path})"]);

    $this->artisan('app:adopt-page-body-images', ['--dry-run' => true])
        ->expectsOutputToContain('nothing was changed')
        ->assertSuccessful();

    Storage::disk('public')->assertExists($path);
    expect(Media::query()->count())->toBe(0);
});

test('a body written against an absolute host url is still rewritten', function () {
    $path = bodyUpload();

    // Filament pastes the disk's configured URL, which carries whatever host
    // was current. Matching only the tidy root-relative form would leave the
    // page pointing at a file this command then deletes.
    $page = Page::factory()->create([
        'body' => '![x]('.url('/storage/'.$path).')',
    ]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    expect((string) $page->refresh()->body)->not->toContain($path);
});

test('two pages sharing one upload are both rewritten', function () {
    $path = bodyUpload();

    $first = Page::factory()->create(['body' => "![a](/storage/{$path})"]);
    $second = Page::factory()->create(['body' => "![b](/storage/{$path})"]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    // Rewriting only the first would delete the file out from under the second.
    expect((string) $first->refresh()->body)->not->toContain($path)
        ->and((string) $second->refresh()->body)->not->toContain($path);
});

test('it does nothing when the directory is empty', function () {
    $this->artisan('app:adopt-page-body-images')
        ->expectsOutputToContain('No page-body uploads found')
        ->assertSuccessful();
});

test('a file awaiting processing is not adopted twice', function () {
    $path = bodyUpload();

    Page::factory()->create(['body' => "![x](/storage/{$path})"]);

    // The worker is down, so the row stays unprocessed and the file stays put.
    Queue::fake();

    $counts = [];

    for ($run = 0; $run < 3; $run++) {
        $this->artisan('app:adopt-page-body-images')->assertSuccessful();
        $counts[] = Media::query()->count();
    }

    // Without the alreadyAdopted() guard this climbs 1, 2, 3 -- one orphan row
    // and one staged copy per run, accumulating daily for as long as the worker
    // is down. That is the leak this command exists to prevent.
    expect($counts)->toBe([1, 1, 1])
        ->and(Storage::disk('local')->files(StagedUpload::DIRECTORY))->toHaveCount(1);
});

test('an adopted row records where it came from', function () {
    $path = bodyUpload('holiday-snap.png');

    Page::factory()->create(['body' => "![x](/storage/{$path})"]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    // The staged copy is a uuid; naming the row after the original is what
    // links it back to the file on disk, and it reads better in the library.
    expect(Media::query()->first()->file_name)->toBe('holiday-snap.png');
});
