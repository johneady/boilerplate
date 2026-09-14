<?php

use App\Jobs\ProcessUploadedImage;
use App\Livewire\Settings\Profile;
use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('the settings page avatar falls back to the gradient with no avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create();

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

    $user = User::factory()->create();

    // The shell menus resolve the thumb conversion, the preview the full one.
    $this->giveAvatar($user);

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

    $user = User::factory()->create();

    $this->giveAvatar($user);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->call('deleteAvatar')
        ->assertHasNoErrors();

    expect($user->refresh()->avatarUrl())->toBeNull();
    Storage::disk('public')->assertMissing('avatars/1/abc/thumb.webp');
});

test('the avatar falls back to initials until processing has finished', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    // The window between upload and the worker finishing: the row exists but
    // its conversions are still null, so nothing should be offered as a src.
    Media::factory()->pending()->create([
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    expect($user->avatarUrl())->toBeNull();
});

test('the avatar falls back to initials when a conversion is missing', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    // Processing wrote `full` but not `thumb` -- a set written by an older
    // configuration. Asking for the missing one must fall back rather than
    // link a file that was never written.
    $this->giveAvatar($user, conversions: ['full']);

    expect($user->avatarUrl('thumb'))->toBeNull()
        ->and($user->avatarUrl('full'))->toContain('avatars/1/abc/full.webp');
});

test('the avatar url resolves once conversions exist', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->giveAvatar($user);

    expect($user->avatarUrl())->toContain('avatars/1/abc/thumb.webp');
});

test('removing an avatar cancels an upload still on the queue', function () {
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create();

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
    expect($user->refresh()->avatarUrl())->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('verification resends are throttled like the route they replace', function () {
    Notification::fake();

    // Fortify throttles its own resend route (6 per minute); the profile
    // banner's Livewire action must not be the unthrottled way around it --
    // an unverified user scripting the link would otherwise send unlimited
    // mail from their own account.
    $user = User::factory()->unverified()->create();

    Livewire::actingAs($user);

    for ($attempt = 0; $attempt < 6; $attempt++) {
        Livewire::test(Profile::class)
            ->call('resendVerificationNotification')
            ->assertHasNoErrors();
    }

    Livewire::test(Profile::class)
        ->call('resendVerificationNotification')
        ->assertHasErrors(['email']);

    Notification::assertSentTimes(VerifyEmail::class, 6);
});
