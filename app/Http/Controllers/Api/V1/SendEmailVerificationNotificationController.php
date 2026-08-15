<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Auth\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Re-sends the email verification notification to the authenticated user.
 *
 * Always answers 204, whether or not a notification was actually sent — an already
 * verified user is not a client error, and the response must not become an oracle.
 */
final class SendEmailVerificationNotificationController
{
    public function __invoke(Request $request): Response
    {
        $user = AuthenticatedUser::from($request);

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }
}
