<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\UserAdvFields;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        abort_if(app(UserService::class)->directoryManagement($user)['managed'], 403);
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->updateAdvancedFields(function (UserAdvFields $fields) use ($user, $input) {
            $user->forceFill(['password' => Hash::make($input['password'])])->save();
            $fields->two_factor_cookies = null;
        });
    }
}
