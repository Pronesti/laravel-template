<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class IssueTokenController
{
    // A precomputed bcrypt hash of a value nothing will ever submit as a password. It
    // exists purely to give Hash::check() something to spend real cycles comparing
    // against when no user is found, so that path costs the same as a genuine wrong
    // password instead of returning near-instantly. Its cost must match the shipped
    // BCRYPT_ROUNDS — tests/Unit/DummyHashTest.php fails if the two ever drift.
    private const string DUMMY_HASH = '$2y$12$nBSmrTxNACTG6WzzpbaPqO1rD0zFYzc2eAhNmP40eAqOXVr8D0cfK';

    public function __invoke(IssueTokenRequest $request): JsonResponse
    {
        $email = (string) $request->string('email');
        $password = (string) $request->string('password');
        $user = User::query()->where('email', $email)->first();

        // The hash comparison always runs, against the real hash when a user exists and
        // against a dummy one when they don't. A single failure mode for "no such user"
        // and "wrong password" — credential probing must not be able to distinguish the
        // two by status, body, or timing.
        $hash = $user instanceof User ? $user->password : self::DUMMY_HASH;
        $validPassword = Hash::check($password, $hash);

        if (! $user instanceof User || ! $validPassword) {
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        return new JsonResponse(
            ['token' => $user->createToken((string) $request->string('device_name'))->plainTextToken],
            201,
        );
    }
}
