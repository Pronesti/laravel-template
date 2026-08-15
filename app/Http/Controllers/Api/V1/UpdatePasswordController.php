<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Auth\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * Token-authenticated replacement for Fortify's PUT user/password.
 *
 * All validation lives in App\Actions\Fortify\UpdateUserPassword, which the container
 * resolves for the UpdatesUserPasswords contract (bound in FortifyServiceProvider).
 */
final class UpdatePasswordController
{
    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, UpdatesUserPasswords $updater): Response
    {
        $updater->update(AuthenticatedUser::from($request), [
            'current_password' => $request->string('current_password')->value(),
            'password' => $request->string('password')->value(),
            'password_confirmation' => $request->string('password_confirmation')->value(),
        ]);

        return response()->noContent();
    }
}
