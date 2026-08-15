<?php

declare(strict_types=1);

use App\Http\Auth\AuthenticatedUser;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

it('returns the user the request resolved', function (): void {
    $user = User::factory()->create();

    $request = Request::create('/api/v1/me');
    $request->setUserResolver(fn (): User => $user);

    expect(AuthenticatedUser::from($request))->toBe($user);
});

it('throws an authentication exception when the request resolves no user', function (): void {
    AuthenticatedUser::from(Request::create('/api/v1/me'));
})->throws(AuthenticationException::class);

it('returns the personal access token the request authenticated with', function (): void {
    $user = User::factory()->create();
    $token = PersonalAccessToken::findToken($user->createToken('test')->plainTextToken);
    assert($token instanceof PersonalAccessToken);

    $request = Request::create('/api/v1/tokens/current');
    $request->setUserResolver(fn (): User => $user->withAccessToken($token));

    expect(AuthenticatedUser::currentToken($request)?->getKey())->toBe($token->getKey());
});
