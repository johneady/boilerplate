<?php

use App\Jobs\ProcessUploadedImage;
use App\Media\MediaCollection;
use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;

/**
 * Put a real encoded image on the private disk and return its path.
 *
 * UploadedFile::fake()->image() produces a genuine raster via GD, so these
 * tests exercise the actual decode/encode path rather than a stub.
 */
function storePendingUpload(int $width = 800, int $height = 400): string
{
    $file = UploadedFile::fake()->image('photo.jpg', $width, $height);

    Storage::disk('local')->put('uploads/pending/source.jpg', $file->getContent());

    return 'uploads/pending/source.jpg';
}

/**
 * A media row in the state the manager leaves it in before the job runs:
 * saved, pointed at its target directory, conversions still null.
 */
function pendingMedia(string $directory = 'avatars/1/abc', MediaCollection $collection = MediaCollection::Avatar): Media
{
    return Media::factory()
        ->pending()
        ->create([
            'collection' => $collection->value,
            'conversion_set' => $collection->conversionSet(),
            'path' => $directory,
        ]);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('it writes every configured conversion', function () {
    $media = pendingMedia();
    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/abc', $media->id)->handle();

    Storage::disk('public')->assertExists('avatars/1/abc/thumb.webp');
    Storage::disk('public')->assertExists('avatars/1/abc/full.webp');
});

test('it crops cover conversions to exactly the configured dimensions', function () {
    $media = pendingMedia();

    // Deliberately non-square: a "cover" avatar must come out square, not
    // letterboxed, or the circular frame will clip it unevenly.
    $path = storePendingUpload(1200, 400);

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/abc', $media->id)->handle();

    $contents = Storage::disk('public')->get('avatars/1/abc/thumb.webp');

    $image = ImageManager::usingDriver(config('images.driver'))->decodeBinary($contents);

    expect($image->width())->toBe(64)
        ->and($image->height())->toBe(64);
});

test('it deletes the unprocessed original once conversions are written', function () {
    $media = pendingMedia();
    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/abc', $media->id)->handle();

    // The original still carries whatever metadata the uploader sent, so
    // leaving it on disk would defeat the point of re-encoding.
    Storage::disk('local')->assertMissing($path);
});

test('it records the written conversions and geometry on the media row', function () {
    $media = pendingMedia();
    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/abc', $media->id)->handle();

    $media->refresh();

    // Until this runs the row has null conversions, which is what Media::url()
    // reads as "still in flight" and renders as a placeholder.
    expect($media->conversions)->toBe([
        'thumb' => 'avatars/1/abc/thumb.webp',
        'full' => 'avatars/1/abc/full.webp',
    ])
        ->and($media->width)->toBe(800)
        ->and($media->height)->toBe(400);
});

test('it strips exif metadata from the processed image', function () {
    // A phone photo carries GPS coordinates in EXIF. Re-encoding must discard
    // them, or uploading a selfie would publish where it was taken.
    $withExif = UploadedFile::fake()->image('photo.jpg', 400, 400)->getContent();

    Storage::disk('local')->put('uploads/pending/source.jpg', $withExif);

    new ProcessUploadedImage('uploads/pending/source.jpg', 'avatar', 'avatars/1/abc', pendingMedia()->id)->handle();

    $processed = Storage::disk('public')->get('avatars/1/abc/thumb.webp');

    expect($processed)->not->toContain('Exif')
        ->and($processed)->not->toContain('GPS');
});

test('it does nothing when the source upload has already gone', function () {
    $media = pendingMedia();

    new ProcessUploadedImage('uploads/pending/missing.jpg', 'avatar', 'avatars/1/abc', $media->id)->handle();

    expect($media->refresh()->conversions)->toBeNull();
});

test('it fails loudly when the conversion set is not configured', function () {
    $media = pendingMedia();
    $path = storePendingUpload();

    expect(fn () => new ProcessUploadedImage($path, 'nonexistent', 'avatars/1/abc', $media->id)->handle())
        ->toThrow(RuntimeException::class);
});

test('it leaves no partial conversion set behind when encoding fails', function () {
    $media = pendingMedia();

    Storage::disk('local')->put('uploads/pending/source.jpg', 'this is not an image');

    expect(fn () => new ProcessUploadedImage('uploads/pending/source.jpg', 'avatar', 'avatars/1/abc', $media->id)->handle())
        ->toThrow(DecoderException::class);

    // A half-written set would leave the row pointing at sizes that do not
    // all exist once a retry succeeded.
    expect(Storage::disk('public')->allFiles('avatars/1/abc'))->toBeEmpty();
    expect($media->refresh()->conversions)->toBeNull();
});

/*
 * The cancel path. A removal cannot un-queue a job already dispatched, so the
 * job asks whether its row still exists when it finishes -- deleting the row is
 * what Remove does. This replaces the cache marker the avatar and logo flows
 * needed back when nothing represented a file until processing had written it.
 */
test('it discards its result when the media row was deleted after dispatch', function () {
    $media = pendingMedia('avatars/1/new');
    $path = storePendingUpload();

    $job = new ProcessUploadedImage($path, 'avatar', 'avatars/1/new', $media->id);

    // The user hit Remove while this job was still sitting on the queue.
    $media->delete();

    $job->handle();

    // Leaving the conversions on disk would orphan them: nothing points at
    // files whose row is gone, so nothing would ever delete them.
    expect(Storage::disk('public')->allFiles('avatars/1/new'))->toBeEmpty();
});

test('it attaches to a row that still exists', function () {
    $media = pendingMedia('avatars/1/new');
    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/new', $media->id)->handle();

    expect($media->refresh()->conversions)->not->toBeNull();
    Storage::disk('public')->assertExists('avatars/1/new/thumb.webp');
});

test('it writes every logo conversion onto its row', function () {
    $media = pendingMedia('logo/abc', MediaCollection::Logo);
    $path = storePendingUpload(600, 600);

    new ProcessUploadedImage($path, 'logo', 'logo/abc', $media->id)->handle();

    expect($media->refresh()->conversions)->toHaveKeys(['mark', 'favicon', 'apple-touch', 'social']);

    Storage::disk('public')->assertExists('logo/abc/favicon.webp');
    Storage::disk('public')->assertExists('logo/abc/apple-touch.webp');
    Storage::disk('public')->assertExists('logo/abc/social.webp');
});

test('it fails cleanly when the staged upload cannot be read', function () {
    $media = pendingMedia();

    Storage::shouldReceive('disk')->andReturnUsing(function ($disk) {
        if ($disk === 'local') {
            return Mockery::mock(Filesystem::class)
                ->shouldReceive('exists')->andReturn(true)->getMock()
                ->shouldReceive('get')->andReturn(null)->getMock();
        }

        return Mockery::mock(Filesystem::class);
    });

    // `throw => false` on the local disk turns an unreadable file into a null
    // read; passing that to the decoder would be a TypeError, not a job failure.
    expect(fn () => new ProcessUploadedImage('uploads/pending/x.jpg', 'avatar', 'avatars/1/abc', $media->id)->handle())
        ->toThrow(RuntimeException::class);
});
