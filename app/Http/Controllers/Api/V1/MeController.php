<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Auth\AuthenticatedUser;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

final class MeController
{
    public function __invoke(Request $request): UserResource
    {
        return new UserResource(AuthenticatedUser::from($request));
    }
}
