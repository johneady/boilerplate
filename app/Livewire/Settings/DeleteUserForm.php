<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ResolvesAuthenticatedUser;
use App\Livewire\Actions\Logout;
use App\Payments\Actions\EndSubscriptionsForDeletedUser;
use App\Payments\Exceptions\GatewayException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules, ResolvesAuthenticatedUser;

    public string $password = '';

    /**
     * Whether this account may be closed from here at all.
     *
     * Staff accounts, and accounts named on financial records, are closed by
     * an administrator instead; see User::canCloseOwnAccount(). The view shows
     * them a note in place of the button.
     */
    #[Computed]
    public function canCloseAccount(): bool
    {
        return $this->authenticatedUser()->canCloseOwnAccount();
    }

    /**
     * Delete the currently authenticated user.
     *
     * A running subscription is cancelled first, while the user is still
     * signed in to be told if the gateway refuses; the model's deleting event
     * would otherwise stop the deletion only after they were logged out.
     *
     * The same goes for canCloseAccount(): it is asked again here, before
     * anything happens, because the view hiding the button is no defence
     * against a request that calls this method directly.
     */
    public function deleteUser(Logout $logout, EndSubscriptionsForDeletedUser $endSubscriptions): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = $this->authenticatedUser();

        if (! $user->canCloseOwnAccount()) {
            throw ValidationException::withMessages([
                'password' => __('Your account can only be closed by an administrator.'),
            ]);
        }

        try {
            $endSubscriptions->handle($user);
        } catch (GatewayException $e) {
            report($e);

            throw ValidationException::withMessages([
                'password' => __('Your subscription could not be cancelled just now, so your account has not been deleted. Please try again in a few minutes.'),
            ]);
        }

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}
