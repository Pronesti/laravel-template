<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

/**
 * The named `verification.notice` route Laravel's `verified` middleware redirects
 * non-JSON requests to. Always answers 403 — a token client never needs it: check
 * `email_verified` on GET /api/v1/me instead. A controller rather than a route
 * closure so `php artisan route:cache` can serialize the route table.
 */
final class VerificationNoticeController
{
    public function __invoke(): never
    {
        abort(403, 'Your email address is not verified.');
    }
}
