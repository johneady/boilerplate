<?php

namespace App\Livewire\Settings;

use App\Audit\AuditEvent;
use App\Audit\AuditLogger;
use App\Concerns\PasswordValidationRules;
use App\Concerns\RendersSettingsChrome;
use App\Concerns\ResolvesAuthenticatedUser;
use App\Settings\Settings;
use Exception;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Security settings')]
class Security extends Component
{
    use PasswordValidationRules, RendersSettingsChrome, ResolvesAuthenticatedUser;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[Locked]
    public bool $canManageTwoFactor;

    #[Locked]
    public bool $twoFactorEnabled;

    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showModal = false;

    public bool $showVerificationStep = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    #[Locked]
    public bool $canManagePasskeys;

    /**
     * @var array<int, array{id: int, name: string, authenticator: string|null, created_at_diff: string|null, last_used_at_diff: string|null}>
     */
    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null($this->authenticatedUser()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication($this->authenticatedUser());
            }

            $this->twoFactorEnabled = $this->authenticatedUser()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        $user = $this->authenticatedUser();

        // A password change must not be outlived by a stolen session or
        // "remember me" cookie: purge every other database-backed session and
        // rotate the remember token alongside the password itself.
        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $user->getAuthIdentifier())
                ->whereNot('id', session()->getId())
                ->delete();
        }

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::ulid(),
        ])->save();

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = $this->authenticatedUser()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn (Passkey $passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                // Localised through the settings service so the words follow
                // the locale setting the same way formatted dates do.
                'created_at_diff' => app(Settings::class)->formatRelative($passkey->created_at),
                'last_used_at_diff' => app(Settings::class)->formatRelative($passkey->last_used_at),
            ])
            ->all();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = $this->authenticatedUser()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $user = $this->authenticatedUser();
        $passkey = $user->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey($user, $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        $enableTwoFactorAuthentication($this->authenticatedUser());

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = $this->authenticatedUser()->hasEnabledTwoFactorAuthentication();

            // Only when confirmation is switched off, because only then has
            // enabling actually taken effect here. With confirmation required
            // this call has merely generated a secret the user may never
            // confirm, and an audit entry saying two-factor was enabled would
            // be wrong. confirmTwoFactor() records that case instead.
            $this->recordTwoFactorAudit(AuditEvent::TwoFactorEnabled);
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = $this->authenticatedUser();

        try {
            $secret = $user->two_factor_secret;

            if ($secret === null) {
                throw new Exception('Two-factor authentication is not configured.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication($this->authenticatedUser(), $this->code);

        $this->closeModal();

        $this->twoFactorEnabled = true;

        $this->recordTwoFactorAudit(AuditEvent::TwoFactorEnabled);
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $wasEnabled = $this->authenticatedUser()->hasEnabledTwoFactorAuthentication();

        $disableTwoFactorAuthentication($this->authenticatedUser());

        $this->twoFactorEnabled = false;

        // Guarded on the prior state: mount() calls this action's underlying
        // work to clear an unconfirmed secret on every page load, and recording
        // that would fill the trail with disables the user never performed.
        if ($wasEnabled) {
            $this->recordTwoFactorAudit(AuditEvent::TwoFactorDisabled);
        }
    }

    /**
     * Write one two-factor change to the audit trail.
     *
     * These are the events AuditEvent declares for the purpose, and without
     * this nothing ever wrote them: the panel offered "Two-factor enabled" as a
     * filter that always returned nothing, reading as "no one has ever changed
     * their two-factor" rather than "this is not recorded". The underlying
     * model write cannot stand in for them -- every column it touches is on the
     * audit denylist, so it redacts to an entry with no content at all.
     *
     * The actor is passed explicitly: this is always the user acting on their
     * own account, and saying so beats relying on the guard lookup.
     */
    private function recordTwoFactorAudit(AuditEvent $event): void
    {
        app(AuditLogger::class)->record($event, [], $this->authenticatedUser());
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = $this->authenticatedUser()->hasEnabledTwoFactorAuthentication();
        }
    }

    /**
     * Get the current modal configuration state.
     *
     * @return array{title: string, description: string, buttonText: string}
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }

    /**
     * @return view-string
     */
    protected function bareView(): string
    {
        return 'partials.settings.security';
    }

    /**
     * @return view-string
     */
    protected function chromedView(): string
    {
        return 'livewire.settings.security';
    }
}
