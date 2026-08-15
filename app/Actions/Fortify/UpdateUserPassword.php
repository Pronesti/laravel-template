<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

final class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            // Deliberately NOT Laravel's `current_password` rule: that rule resolves the user
            // from a guard, which couples this action to whichever guard happens to be active
            // — 'sanctum' here, but 'web' if a project ever re-enables Fortify's own session
            // route. The action is handed the $user directly, so verify against that hash and
            // the coupling disappears in both directions.
            'current_password' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    if (! is_string($value) || ! Hash::check($value, (string) $user->password)) {
                        $fail(__('The provided password does not match your current password.'));
                    }
                },
            ],
            'password' => $this->passwordRules(),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
