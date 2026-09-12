<?php

use App\Jobs\ProcessUploadedImage;
use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('the settings page avatar falls back to the gradient with no avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => null]);

    $html = $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->getContent();

    // The dashboard tests cover the four user-menu renders; this page adds the
    // fifth -- the xl preview next to the "Change photo" button. Counting the
    // avatar elements painted with the gradient (four menu renders plus the
    // preview) proves this site fell back too rather than riding on the
    // shell's gradients.
    $gradientStyle = preg_quote($user->avatarGradientStyle(), '/');

    expect(preg_match_all('/<[^>]*data-flux-avatar[^>]*\bstyle="'.$gradientStyle.';?"/', $html))->toBe(5);
});

test('the settings page avatar shows the processed image without the gradient', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/1/abc']);

    // The shell menus resolve the thumb conversion, the preview the full one.
    Storage::disk('public')->put('avatars/1/abc/thumb.webp', 'processed');
    Storage::disk('public')->put('avatars/1/abc/full.webp', 'processed');

    $html = $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('src="/storage/avatars/1/abc/full.webp"')
        ->and($html)->not->toContain($user->avatarGradientStyle());
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('an avatar upload is queued rather than processed in the request', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('photo.jpg', 400, 400))
        ->call('updateAvatar')
        ->assertHasNoErrors();

    Queue::assertPushed(ProcessUploadedImage::class);
});

test('an uploaded avatar is staged on the private disk, never the public one', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('photo.jpg', 400, 400))
        ->call('updateAvatar');

    // Serving the original before it has been re-encoded would hand out the
    // uploader's EXIF and anything else embedded in the file.
    expect(Storage::disk('local')->allFiles('uploads/pending'))->toHaveCount(1);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('an svg is rejected even though it is a valid image', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user);

    // Real SVG bytes, not an empty fake: an empty file is rejected by almost
    // any rule, which would let this test pass without proving anything about
    // SVG. An SVG can carry <script> and is served from this application's own
    // origin, so the explicit format allow-list must exclude it -- Laravel's
    // `image` rule would accept this same file under `image:allow_svg`.
    $svg = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
            <script>alert(document.cookie)</script>
            <rect width="100" height="100" fill="red"/>
        </svg>
        SVG;

    $file = UploadedFile::fake()->createWithContent('payload.svg', $svg);

    Livewire::test(Profile::class)
        ->set('avatar', $file)
        ->call('updateAvatar')
        ->assertHasErrors(['avatar']);

    Queue::assertNotPushed(ProcessUploadedImage::class);
});

test('an oversized upload is rejected', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user);

    $oversized = config('images.max_kilobytes') + 1;

    Livewire::test(Profile::class)
        ->set('avatar', UploadedFile::fake()->create('huge.jpg', $oversized, 'image/jpeg'))
        ->call('updateAvatar')
        ->assertHasErrors(['avatar']);

    Queue::assertNotPushed(ProcessUploadedImage::class);
});

test('a user can remove their avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/1/abc']);

    Storage::disk('public')->put('avatars/1/abc/thumb.webp', 'processed');

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->call('deleteAvatar')
        ->assertHasNoErrors();

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('avatars/1/abc/thumb.webp');
});

test('the avatar falls back to initials until processing has finished', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => null]);

    // The window between upload and the worker finishing: no conversion exists
    // yet, so nothing should be offered as a src.
    expect($user->avatarUrl())->toBeNull();
});

test('the avatar falls back to initials when a conversion is missing', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/1/abc']);

    expect($user->avatarUrl())->toBeNull();
});

test('the avatar url resolves once conversions exist', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/1/abc']);

    Storage::disk('public')->put('avatars/1/abc/thumb.webp', 'processed');

    expect($user->avatarUrl())->toContain('avatars/1/abc/thumb.webp');
});

test('removing an avatar cancels an upload still on the queue', function () {
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => null]);

    $this->actingAs($user);

    // The real sequence: upload, then remove before the worker has run. The
    // job is captured rather than faked away so it can be run afterwards,
    // exactly as a worker picking it up late would.
    $dispatched = null;

    Queue::fake();

    Livewire::test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('photo.jpg', 400, 400))
        ->call('updateAvatar')
        ->call('deleteAvatar');

    Queue::assertPushed(ProcessUploadedImage::class, function ($job) use (&$dispatched) {
        $dispatched = $job;

        return true;
    });

    // The worker catches up only now, after the removal.
    $dispatched->handle();

    // The deleted avatar must not come back, and the conversions the job wrote
    // must not be left orphaned on disk.
    expect($user->refresh()->avatar_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});
