<?php

namespace App\Livewire\Settings;

use App\Concerns\ImageValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\RendersSettingsChrome;
use App\Concerns\ResolvesAuthenticatedUser;
use App\Jobs\ProcessUploadedImage;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Profile settings')]
class Profile extends Component
{
    use ImageValidationRules, ProfileValidationRules, RendersSettingsChrome, ResolvesAuthenticatedUser, WithFileUploads;

    public string $name = '';

    public string $email = '';

    /**
     * A newly selected avatar, before it is uploaded and queued.
     */
    public ?TemporaryUploadedFile $avatar = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = $this->authenticatedUser()->name;
        $this->email = $this->authenticatedUser()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = $this->authenticatedUser();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = $this->authenticatedUser();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    /**
     * Validate a newly selected avatar as soon as it is chosen.
     *
     * Without this the user picks a file, submits, and only then learns the
     * file was rejected.
     */
    public function updatedAvatar(): void
    {
        $this->validateOnly('avatar', ['avatar' => $this->imageRules()]);
    }

    /**
     * Queue processing for a newly uploaded avatar.
     *
     * The upload is moved to the private disk and handed to a job rather than
     * processed inline: decoding a large image is slow enough to hold a web
     * request, and the worker role exists for exactly this.
     */
    public function updateAvatar(): void
    {
        $this->validate(['avatar' => $this->imageRules()]);

        $user = $this->authenticatedUser();

        /** @var TemporaryUploadedFile $avatar */
        $avatar = $this->avatar;

        // storeAs() on the private disk, NOT the public one: the unprocessed
        // original must never be reachable over HTTP.
        $sourcePath = $avatar->storeAs(
            'uploads/pending',
            Str::uuid()->toString(),
            ['disk' => 'local'],
        );

        ProcessUploadedImage::dispatch(
            sourcePath: (string) $sourcePath,
            conversionSet: 'avatar',
            targetDirectory: 'avatars/'.$user->id.'/'.Str::uuid()->toString(),
            userId: $user->id,
        );

        $this->reset('avatar');

        Flux::toast(text: __('Avatar uploaded. It will appear shortly.'));
    }

    /**
     * Remove the current avatar and its conversions.
     */
    public function deleteAvatar(): void
    {
        $user = $this->authenticatedUser();

        $directory = $user->avatar_path;

        // The marker is recorded even when there is nothing on disk yet. An
        // upload queued moments ago has avatar_path still null -- returning
        // early here would skip the marker in exactly the case it exists for,
        // and the job would attach an avatar the user had already removed.
        Cache::put(
            ProcessUploadedImage::removalKey($user->id),
            time(),
            now()->addDay(),
        );

        if ($directory !== null) {
            $user->forceFill(['avatar_path' => null])->save();

            /** @var string $disk */
            $disk = config('images.disk');

            Storage::disk($disk)->deleteDirectory($directory);
        }

        Flux::toast(variant: 'success', text: __('Avatar removed.'));
    }

    #[Computed]
    public function avatarUrl(): ?string
    {
        return $this->authenticatedUser()->avatarUrl('full');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return ! $this->authenticatedUser()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return $this->authenticatedUser()->hasVerifiedEmail();
    }

    /**
     * @return view-string
     */
    protected function bareView(): string
    {
        return 'partials.settings.profile';
    }

    /**
     * @return view-string
     */
    protected function chromedView(): string
    {
        return 'livewire.settings.profile';
    }
}
