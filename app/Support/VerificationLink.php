<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * The email-verification link: every rule about its shape lives here and nowhere else.
 *
 * The link is a RELATIVE signed route (so the FRONTEND_URL host can be prefixed for the
 * mailed URL), keyed on the user's external_id (never the bigint primary key) plus a
 * sha1 of the email it verifies, expiring after auth.verification.expire minutes. The
 * `signed:relative` middleware on the verification.verify route validates what
 * signedPath() produces; matches() answers the hash half of that proof.
 *
 * Typed against the framework contracts, not App\Models\User: App\Support must stay
 * out of the persistence layer (see the arch rule in tests/Arch.php).
 */
final class VerificationLink
{
    public static function mint(MustVerifyEmail&UrlRoutable $user): string
    {
        return rtrim(Config::string('app.frontend_url'), '/').self::signedPath($user);
    }

    public static function signedPath(MustVerifyEmail&UrlRoutable $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(Config::integer('auth.verification.expire', 60)),
            [
                'user' => $user->getRouteKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
            absolute: false,
        );
    }

    public static function matches(MustVerifyEmail $user, string $hash): bool
    {
        return hash_equals(sha1($user->getEmailForVerification()), $hash);
    }
}
