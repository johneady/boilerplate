<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ResolvesAuthenticatedUser;
use App\Livewire\Actions\Logout;
use App\Payments\Actions\EndSubscriptionsForDeletedUser;
use App\Payments\Exceptions\GatewayException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules, ResolvesAuthenticatedUser;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     *
     * A running subscription is cancelled first, while the user is still
     * signed in to be told if the gateway refuses; the model's deleting event
     * would otherwise stop the deletion only after they were logged out.
     */
    public function deleteUser(Logout $logout, EndSubscriptionsForDeletedUser $endSubscriptions): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = $this->authenticatedUser();

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
