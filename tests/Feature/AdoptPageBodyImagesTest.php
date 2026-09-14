<?php

use App\Console\Commands\AdoptPageBodyImages;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Jobs\ProcessUploadedImage;
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

    // The row is deliberately OWNERLESS: what owns a body image is the body
    // text naming it, not a record -- attaching it to the page would delete
    // the files with that page while other bodies still render them.
    expect($media)->not->toBeNull()
        ->and($media->model_id)->toBeNull()
        ->and($media->model_type)->toBeNull();

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

test('a body written against a different host is still rewritten', function () {
    // A prod-shaped disk URL: Storage::fake() defaults to a RELATIVE /storage
    // root, which makes the replacement relative too and hides exactly the
    // corruption this test exists to catch -- an absolute foreign host left
    // prefixed to an absolute replacement URL when the bare /storage/ form is
    // replaced before the host-prefixed one.
    Storage::fake('public', ['url' => 'https://app.example.com/storage']);

    $path = bodyUpload();

    // APP_URL changes, or a body saved from another environment, leave URLs
    // carrying a host the current config will never produce.
    $page = Page::factory()->create([
        'body' => "![x](https://staging.example.test/storage/{$path})",
    ]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    $media = Media::query()->inCollection(MediaCollection::PageImage)->first();

    // The whole old URL is replaced by the whole new one -- not a splice of
    // the two -- and the original is gone.
    expect((string) $page->refresh()->body)
        ->toBe('![x]('.(string) $media->url('wide').')')
        ->and(Storage::disk('public')->assertMissing($path));
});

test('the original is kept when a body still names it in an unmatched form', function () {
    $path = bodyUpload();

    // A body may name the stored path without any /storage/ prefix -- plain
    // prose quoting the path counts as a reference. Deleting the original
    // would break that page, so the file stays and the run reports it.
    $page = Page::factory()->create([
        'body' => "![x](/storage/{$path}) and see also {$path} on disk",
    ]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    expect((string) $page->refresh()->body)->toContain($path)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
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

test('deleting one page sharing an upload leaves the other its image', function () {
    $path = bodyUpload();

    $first = Page::factory()->create(['body' => "![a](/storage/{$path})"]);
    $second = Page::factory()->create(['body' => "![b](/storage/{$path})"]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    $media = Media::query()->inCollection(MediaCollection::PageImage)->first();

    // The row is ownerless precisely so this cascade cannot happen: attached
    // to the first page, deleting that page would take the conversions with
    // it and break the second page.
    $first->delete();

    expect((string) $second->refresh()->body)->toContain((string) $media->refresh()->url('wide'))
        ->and(Storage::disk('public')->exists($media->path('wide')))->toBeTrue();
});

test('a non-numeric hours option is refused rather than treated as a sweep', function () {
    $path = bodyUpload();

    // (int) 'soon' is 0, and here 0 means "collect everything unreferenced
    // now" -- the opposite of app:prune-orphaned-media, where 0 disables. A
    // typo must fail loudly rather than sweep.
    $this->artisan('app:adopt-page-body-images', ['--hours' => 'soon'])
        ->expectsOutputToContain('whole number')
        ->assertFailed();

    expect(Storage::disk('public')->exists($path))->toBeTrue();
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

test('a run resumes an adoption whose processing has since finished', function () {
    $path = bodyUpload();

    $page = Page::factory()->create(['body' => "![x](/storage/{$path})"]);

    // First run: the worker is down, so the row is written but no conversions
    // exist and the body still names the original.
    Queue::fake();
    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    expect($page->refresh()->body)->toContain($path)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();

    // The worker catches up, running the job exactly as it was queued.
    $media = Media::query()->first();
    (new ProcessUploadedImage(
        sourcePath: Storage::disk('local')->files(StagedUpload::DIRECTORY)[0],
        conversionSet: (string) $media->conversion_set,
        targetDirectory: $media->path,
        mediaId: $media->getKey(),
    ))->handle();

    // Second run must finish the job the first could not: without this the
    // early return skipped the file forever, leaving the page serving the
    // original (EXIF intact) and the file uncollectable.
    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    expect($page->refresh()->body)->not->toContain($path)
        ->and($page->body)->toContain($media->refresh()->url('wide'))
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and(Media::query()->count())->toBe(1);
});

test('an adopted file whose pages no longer reference it is left to the row', function () {
    $path = bodyUpload();

    $page = Page::factory()->create(['body' => "![x](/storage/{$path})"]);

    Queue::fake();
    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    // The author removes the image from the body before the worker catches up.
    $page->forceFill(['body' => 'no images here'])->save();

    $this->artisan('app:adopt-page-body-images')
        ->expectsOutputToContain('no longer referenced')
        ->assertSuccessful();

    // Deleting the file here would strand the row pointing at nothing; the row
    // is now an ordinary unowned-media case for app:prune-orphaned-media.
    expect(Media::query()->count())->toBe(1)
        ->and(Storage::disk('local')->files(StagedUpload::DIRECTORY))->toHaveCount(1);
});

test('the orphan prune collects an adopted row once no body names it', function () {
    $path = bodyUpload();

    $page = Page::factory()->create(['body' => "![x](/storage/{$path})"]);

    $this->artisan('app:adopt-page-body-images')->assertSuccessful();

    $media = Media::query()->inCollection(MediaCollection::PageImage)->first();

    Storage::disk('public')->assertMissing($path);

    // The author removes the image from the body after the rewrite.
    $page->forceFill(['body' => 'no images here'])->save();

    // Past the retention window the row is ordinary unowned media: nothing
    // owns it and no body names it.
    $media->forceFill(['created_at' => now()->subDays(2)])->save();

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->assertMissing($media->path));
});
