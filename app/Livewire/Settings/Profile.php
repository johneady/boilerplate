<?php

namespace App\Livewire\Settings;

use App\Concerns\ImageValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\RendersSettingsChrome;
use App\Concerns\ResolvesAuthenticatedUser;
use App\Media\MediaCollection;
use App\Media\MediaManager;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
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
     * How many verification emails one account may request per minute.
     *
     * Matches the throttle Fortify puts on its own resend route, which this
     * action otherwise bypasses: an unverified user scripting the link would
     * otherwise send unlimited mail from their own account.
     */
    private const int MAX_RESENDS_PER_MINUTE = 6;

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

        $this->ensureResendIsNotRateLimited($user->id);

        RateLimiter::increment($this->resendLimitKey($user->id), 60);

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    /**
     * Refuse a resend once the account has requested too many.
     *
     * Keyed on the account rather than the session: the flood this bounds is
     * self-targeted, and a script does not keep cookies.
     */
    private function ensureResendIsNotRateLimited(int $userId): void
    {
        if (! RateLimiter::tooManyAttempts($this->resendLimitKey($userId), self::MAX_RESENDS_PER_MINUTE)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('You have requested several verification emails recently. Please try again later.'),
        ]);
    }

    /**
     * The rate limiter key for this account's verification resends.
     */
    private function resendLimitKey(int $userId): string
    {
        return 'verification-resend:'.$userId;
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
     * The manager stages the upload on the private disk and hands it to a job
     * rather than processing inline: decoding a large image is slow enough to
     * hold a web request, and the worker role exists for exactly this. The
     * media row is written immediately, so the avatar exists as a record
     * before its conversions do.
     */
    public function updateAvatar(): void
    {
        $this->validate(['avatar' => $this->imageRules()]);

        /** @var TemporaryUploadedFile $avatar */
        $avatar = $this->avatar;

        app(MediaManager::class)->attach(
            file: $avatar,
            collection: MediaCollection::Avatar,
            owner: $this->authenticatedUser(),
        );

        $this->reset('avatar');

        Flux::toast(text: __('Avatar uploaded. It will appear shortly.'));
    }

    /**
     * Remove the current avatar and its conversions.
     *
     * Deleting the row is also what cancels an upload still being processed:
     * the job looks its row up when it finishes and discards the conversions
     * when it is gone. That replaces the cache marker this flow used to need,
     * back when nothing represented an avatar until processing had finished.
     */
    public function deleteAvatar(): void
    {
        $this->authenticatedUser()->clearMedia(MediaCollection::Avatar);

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
