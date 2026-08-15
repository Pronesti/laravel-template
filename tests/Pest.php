<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\VerificationLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * The signed API path a frontend posts back to verify an email — what
 * VerificationLink::mint() prepends the FRONTEND_URL host to when mailing it.
 *
 * Lives here rather than in a test file so it is declared exactly once: a second test
 * file declaring the same global name would be a fatal redeclaration error.
 */
function verificationPathFor(User $user): string
{
    return VerificationLink::signedPath($user);
}

/**
 * Issues a real PersonalAccessToken for the user and returns its plain-text value.
 *
 * This is the one way tests authenticate. Sanctum::actingAs() installs a
 * TransientToken, which is not what production requests carry — code guarded by
 * `instanceof PersonalAccessToken` (RevokeTokenController) silently no-ops under it,
 * so tests built on actingAs() can pass without executing the code under test.
 */
function tokenFor(User $user): string
{
    return $user->createToken('test')->plainTextToken;
}
