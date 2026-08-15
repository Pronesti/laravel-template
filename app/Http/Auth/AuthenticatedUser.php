<?php

declare(strict_types=1);

namespace App\Http\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The one place that turns "this route sits behind auth:sanctum" into a typed User.
 *
 * Controllers must not restate this narrowing themselves (assert(), instanceof
 * checks, nullsafe chains): behind auth:sanctum the throw is unreachable, and if a
 * route is ever misregistered without the guard it becomes the same 401
 * problem+json the middleware would have produced — never a silent no-op.
 */
final class AuthenticatedUser
{
    /**
     * @throws AuthenticationException
     */
    public static function from(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * The persisted token the request authenticated with, or null when it carries a
     * TransientToken instead — Sanctum installs one in cookie/SPA mode, and it has no
     * database row to revoke.
     *
     * @throws AuthenticationException
     */
    public static function currentToken(Request $request): ?PersonalAccessToken
    {
        self::from($request);

        // Read the token off the request rather than the returned User: Sanctum's
        // HasApiTokens generic defaults to PersonalAccessToken, which would make this
        // narrowing look redundant to static analysis when at runtime it is not.
        $token = $request->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }
}
