<?php

use App\Media\MediaCollection;
use App\Media\StagedUpload;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

test('it deletes an unattached row past the grace period, and its files', function () {
    $media = Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subDays(2),
    ]);

    Storage::disk('public')->put($media->path.'/thumb.webp', 'x');

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($media->path.'/thumb.webp');
});

test('it keeps an unattached row still inside the grace period', function () {
    // The row is written before its owner exists, so a fresh unattached row is
    // indistinguishable from a create form still open in somebody's browser.
    Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subMinutes(5),
    ]);

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(1);
});

test('it never touches a row that still has an owner', function () {
    $user = User::factory()->create();

    Media::factory()->create([
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
        'created_at' => now()->subYear(),
    ]);

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(1);
});

test('the grace period can be overridden for a one-off sweep', function () {
    Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('app:prune-orphaned-media', ['--hours' => 1])->assertSuccessful();

    expect(Media::query()->count())->toBe(0);
});

test('a zero retention disables pruning entirely', function () {
    Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subYear(),
    ]);

    $this->artisan('app:prune-orphaned-media', ['--hours' => 0])
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();

    expect(Media::query()->count())->toBe(1);
});

test('it deletes past a single chunk', function () {
    Media::factory()->count(150)->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subDays(2),
    ]);

    // chunkById rather than chunk: rows are deleted as it goes, so an
    // offset chunk would skip every second page.
    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(0);
});

test('it spares a row a page body still names, ownerless or not', function () {
    // Adopted page-body images are deliberately ownerless -- their "owner"
    // is the body text referencing their URL -- so the body check is what
    // keeps a referenced image alive past the retention window.
    $media = Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subDays(2),
    ]);

    Storage::disk('public')->put($media->path.'/wide.webp', 'x');

    Page::factory()->create(['body' => '![x]('.Storage::disk('public')->url($media->path.'/wide.webp').')']);

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(1)
        ->and(Storage::disk('public')->exists($media->path.'/wide.webp'))->toBeTrue();
});

test('it never collects the installation logo, however old', function () {
    // The logo is ownerless BY DESIGN -- it belongs to the installation, not
    // to a record -- so it matches the orphaned scope forever. Collecting it
    // would silently strip the brand from every page ~24h after an upload.
    $logo = Media::factory()
        ->logo()
        ->create(['created_at' => now()->subYear()]);

    Storage::disk('public')->put($logo->path.'/mark.webp', 'x');

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->inCollection(MediaCollection::Logo)->count())->toBe(1)
        ->and(Storage::disk('public')->exists($logo->path.'/mark.webp'))->toBeTrue();
});

test('an abandoned staged upload is swept past the window, and kept inside it', function () {
    $old = StagedUpload::DIRECTORY.'/'.Str::uuid()->toString();
    $fresh = StagedUpload::DIRECTORY.'/'.Str::uuid()->toString();

    Storage::disk('local')->put($old, 'x');
    Storage::disk('local')->put($fresh, 'x');

    // lastModified comes from the filesystem, and Carbon's clock does not
    // move it, so the stale file's mtime is set directly.
    touch(Storage::disk('local')->path($old), now()->subHours(2)->getTimestamp());

    $this->artisan('app:prune-orphaned-media', ['--hours' => 1])->assertSuccessful();

    // The fresh file may belong to a job still queued behind a slow worker;
    // the old one is abandoned -- no row points at it and nothing else reads
    // the staging directory.
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($fresh);
});
