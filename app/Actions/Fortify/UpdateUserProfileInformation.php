<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\UserAdvFields;
use App\Services\LdapUserAuthenticator;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update(User $user, array $input): void
    {
        Validator::make($input, ['section' => ['required', 'in:name,email']])->validate();
        $directory = app(UserService::class)->directoryManagement($user);

        if ($input['section'] === 'name') {
            abort_if($directory['managed'], 403);
            $names = Validator::make($input, [
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['nullable', 'string', 'max:255'],
            ])->validate();
            $user->updateAdvancedFields(fn (UserAdvFields $fields) => $fields->fill($names));
            return;
        }

        abort_if($directory['email_managed'], 403);
        $data = Validator::make($input, [
            'user_email' => ['required', 'string', 'max:255', 'email'],
            'current_password' => ['required', 'string'],
        ])->validate();
        if (! app(LdapUserAuthenticator::class)->confirmPassword($user, $data['current_password'])) {
            throw ValidationException::withMessages(['current_password' => __('The provided password does not match your current password.')]);
        }

        $email = mb_strtolower(trim($data['user_email']));
        $user->updateAdvancedFields(function (UserAdvFields $fields) use ($user, $email) {
            // Two users can request the same sign-in address from different account rows.
            if (DB::getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['login-email:'.$email]);
            }
            if (User::whereRaw('LOWER(user_email) = ?', [$email])->whereKeyNot($user->getKey())->exists()) {
                throw ValidationException::withMessages(['user_email' => __('This email address is already in use.')]);
            }
            $user->user_email = $email;
            $user->save();
            $fields->two_factor_cookies = null;
        });
        session()->put('user.user_email', $email);
    }
}
