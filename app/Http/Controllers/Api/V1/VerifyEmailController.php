<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Support\VerificationLink;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Response;

/**
 * Marks a user's email address as verified.
 *
 * Deliberately NOT behind auth:sanctum. The caller is a browser following a link from an
 * email and has no way to attach a bearer token; the signature on the URL (validated by
 * the `signed:relative` middleware) plus the email hash is what proves ownership.
 */
final class VerifyEmailController
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, string $hash): Response
    {
        if (! VerificationLink::matches($user, $hash)) {
            throw new AuthorizationException('Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->noContent();
    }
}
