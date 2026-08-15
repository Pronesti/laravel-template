<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Auth\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RevokeTokenController
{
    public function __invoke(Request $request): Response
    {
        AuthenticatedUser::currentToken($request)?->delete();

        return response()->noContent();
    }
}
