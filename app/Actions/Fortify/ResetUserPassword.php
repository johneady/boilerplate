<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            // A reset must also retire outstanding "remember me" cookies.
            'remember_token' => Str::ulid(),
        ])->save();

        // ...and any session the user still holds elsewhere, since the reset
        // flow cannot know which of them is trustworthy.
        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $user->getAuthIdentifier())
                ->delete();
        }
    }
}
