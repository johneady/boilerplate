<?php

use App\Jobs\ProcessUploadedImage;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('it writes every configured conversion', function () {
    $user = User::factory()->create();
    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/abc', $user->id)->handle();

    Storage::disk('public')->assertExists('avatars/1/abc/thumb.webp');
    Storage::disk('public')->assertExists('avatars/1/abc/full.webp');
});

test('it crops cover conversions to exactly the configured dimensions', function () {
    $user = User::factory()->create();

    // Deliberately non-square: a "cover" avatar must come out square, not
    // letterboxed, or the circular frame will clip it unevenly.
    $path = storePendingUpload(1200, 400);

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/abc', $user->id)->handle();

    $contents = Storage::disk('public')->get('avatars/1/abc/thumb.webp');

    $image = ImageManager::usingDriver(config('images.driver'))->decodeBinary($contents);

    expect($image->width())->toBe(64)
        ->and($image->height())->toBe(64);
});

test('it deletes the unprocessed original once conversions are written', function () {
    $user = User::factory()->create();
    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/abc', $user->id)->handle();

    // The original still carries whatever metadata the uploader sent, so
    // leaving it on disk would defeat the point of re-encoding.
    Storage::disk('local')->assertMissing($path);
});

test('it points the user at the processed directory', function () {
    $user = User::factory()->create(['avatar_path' => null]);
    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/abc', $user->id)->handle();

    expect($user->refresh()->avatar_path)->toBe('avatars/1/abc');
});

test('it removes the previous conversions when an avatar is replaced', function () {
    $user = User::factory()->create(['avatar_path' => 'avatars/1/old']);

    Storage::disk('public')->put('avatars/1/old/thumb.webp', 'stale');

    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/new', $user->id)->handle();

    Storage::disk('public')->assertMissing('avatars/1/old/thumb.webp');
    Storage::disk('public')->assertExists('avatars/1/new/thumb.webp');
});

test('it strips exif metadata from the processed image', function () {
    $user = User::factory()->create();

    // A phone photo carries GPS coordinates in EXIF. Re-encoding must discard
    // them, or uploading a selfie would publish where it was taken.
    $withExif = UploadedFile::fake()->image('photo.jpg', 400, 400)->getContent();

    Storage::disk('local')->put('uploads/pending/source.jpg', $withExif);

    new ProcessUploadedImage('uploads/pending/source.jpg', 'avatar', 'avatars/1/abc', $user->id)->handle();

    $processed = Storage::disk('public')->get('avatars/1/abc/thumb.webp');

    expect($processed)->not->toContain('Exif')
        ->and($processed)->not->toContain('GPS');
});

test('it does nothing when the source upload has already gone', function () {
    $user = User::factory()->create();

    new ProcessUploadedImage('uploads/pending/missing.jpg', 'avatar', 'avatars/1/abc', $user->id)->handle();

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('it fails loudly when the conversion set is not configured', function () {
    $user = User::factory()->create();
    $path = storePendingUpload();

    expect(fn () => new ProcessUploadedImage($path, 'nonexistent', 'avatars/1/abc', $user->id)->handle())
        ->toThrow(RuntimeException::class);
});

test('it leaves no partial conversion set behind when encoding fails', function () {
    $user = User::factory()->create();

    Storage::disk('local')->put('uploads/pending/source.jpg', 'this is not an image');

    expect(fn () => new ProcessUploadedImage('uploads/pending/source.jpg', 'avatar', 'avatars/1/abc', $user->id)->handle())
        ->toThrow(DecoderException::class);

    // A half-written set would leave the model pointing at sizes that do not
    // all exist once a retry succeeded.
    expect(Storage::disk('public')->allFiles('avatars/1/abc'))->toBeEmpty();
    expect($user->refresh()->avatar_path)->toBeNull();
});

test('it discards its result when the avatar was removed after dispatch', function () {
    $user = User::factory()->create(['avatar_path' => null]);
    $path = storePendingUpload();

    $job = new ProcessUploadedImage($path, 'avatar', 'avatars/1/new', $user->id);

    // The user hit Remove while this job was still sitting on the queue.
    Cache::put(ProcessUploadedImage::removalKey($user->id), time() + 1, now()->addDay());

    $job->handle();

    // Re-attaching here would resurrect the avatar the user just deleted.
    expect($user->refresh()->avatar_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('avatars/1/new'))->toBeEmpty();
});

test('it still attaches when a removal predates the dispatch', function () {
    $user = User::factory()->create(['avatar_path' => null]);

    // An older removal must not block a subsequently uploaded avatar.
    Cache::put(ProcessUploadedImage::removalKey($user->id), time() - 60, now()->addDay());

    $path = storePendingUpload();

    new ProcessUploadedImage($path, 'avatar', 'avatars/1/new', $user->id)->handle();

    expect($user->refresh()->avatar_path)->toBe('avatars/1/new');
});

test('it points the site icon setting at the processed directory', function () {
    $path = storePendingUpload(600, 600);

    new ProcessUploadedImage($path, 'logo', 'logo/abc', settingKey: SettingKey::Logo)->handle();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::Logo))->toBe('logo/abc');

    Storage::disk('public')->assertExists('logo/abc/favicon.webp');
    Storage::disk('public')->assertExists('logo/abc/apple-touch.webp');
    Storage::disk('public')->assertExists('logo/abc/social.webp');
});

test('it removes the previous site icon when it is replaced', function () {
    app(Settings::class)->set(SettingKey::Logo, 'logo/old');

    Storage::disk('public')->put('logo/old/favicon.webp', 'stale');

    $path = storePendingUpload(600, 600);

    new ProcessUploadedImage($path, 'logo', 'logo/new', settingKey: SettingKey::Logo)->handle();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::Logo))->toBe('logo/new');
    Storage::disk('public')->assertMissing('logo/old/favicon.webp');
    Storage::disk('public')->assertExists('logo/new/favicon.webp');
});

test('it discards its result when the site icon was removed after dispatch', function () {
    app(Settings::class)->set(SettingKey::Logo, 'logo/old');

    $path = storePendingUpload(600, 600);

    $job = new ProcessUploadedImage($path, 'logo', 'logo/new', settingKey: SettingKey::Logo);

    // The administrator hit Remove while this job was still on the queue.
    Cache::put(ProcessUploadedImage::settingRemovalKey(SettingKey::Logo), time() + 1, now()->addDay());

    $job->handle();

    // Re-attaching here would resurrect the icon the administrator deleted.
    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::Logo))->toBe('logo/old');
    expect(Storage::disk('public')->allFiles('logo/new'))->toBeEmpty();
});

test('it still attaches a site icon when a removal predates the dispatch', function () {
    Cache::put(ProcessUploadedImage::settingRemovalKey(SettingKey::Logo), time() - 60, now()->addDay());

    $path = storePendingUpload(600, 600);

    new ProcessUploadedImage($path, 'logo', 'logo/new', settingKey: SettingKey::Logo)->handle();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::Logo))->toBe('logo/new');
});

test('it fails cleanly when the staged upload cannot be read', function () {
    $user = User::factory()->create();

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
    expect(fn () => new ProcessUploadedImage('uploads/pending/x.jpg', 'avatar', 'avatars/1/abc', $user->id)->handle())
        ->toThrow(RuntimeException::class);
});
