<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Auth\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Token-authenticated replacement for Fortify's PUT user/profile-information.
 *
 * All validation lives in App\Actions\Fortify\UpdateUserProfileInformation, which the
 * container resolves for the UpdatesUserProfileInformation contract (bound in
 * FortifyServiceProvider). This controller only adapts the request to that contract.
 */
final class UpdateProfileInformationController
{
    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, UpdatesUserProfileInformation $updater): Response
    {
        // Narrowed to strings on purpose: the action's contract takes array<string, string>
        // and does the validating. Absent fields arrive as '' and fail `required` there.
        $updater->update(AuthenticatedUser::from($request), [
            'name' => $request->string('name')->value(),
            'email' => $request->string('email')->value(),
        ]);

        return response()->noContent();
    }
}
