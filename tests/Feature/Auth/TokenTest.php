<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

it('issues a token for valid credentials', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'password' => 'password-password']);

    $response = $this->postJson('/api/v1/tokens', [
        'email' => 'ada@example.com',
        'password' => 'password-password',
        'device_name' => 'integration-test',
    ])->assertCreated()->assertJsonStructure(['token']);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

it('rejects invalid credentials as a 422 problem', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'password' => 'password-password']);

    $this->postJson('/api/v1/tokens', [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
        'device_name' => 'integration-test',
    ])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('errors.email.0', trans('auth.failed'));
});

it('pays the same hash-comparison cost for an unknown email as for a wrong password', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'password' => 'password-password']);

    // A short-circuit on "no such user" would skip Hash::check entirely, making a
    // nonexistent email measurably faster to reject than a wrong password for a real
    // account — an account-enumeration timing oracle. The comparison must always run;
    // Mockery verifies the "exactly once" expectation when the test tears down.
    Hash::shouldReceive('check')->once()->andReturn(false);

    $this->postJson('/api/v1/tokens', [
        'email' => 'nobody@example.com',
        'password' => 'password-password',
        'device_name' => 'integration-test',
    ])->assertStatus(422);
});

it('refuses to issue a token to a soft deleted user', function (): void {
    User::factory()->create(['email' => 'gone@example.com', 'password' => 'password-password'])->delete();

    // The row must still exist (soft deleted) — otherwise this proves nothing about the
    // global scope, only that a hard-deleted user can't authenticate.
    expect(User::withTrashed()->where('email', 'gone@example.com')->exists())->toBeTrue();

    $this->postJson('/api/v1/tokens', [
        'email' => 'gone@example.com',
        'password' => 'password-password',
        'device_name' => 'integration-test',
    ])->assertStatus(422);
});

it('revokes the calling token', function (): void {
    $user = User::factory()->create();
    $token = tokenFor($user);

    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('rejects requests made with a revoked token', function (): void {
    $user = User::factory()->create();
    $token = tokenFor($user);

    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();

    // Guard instances can cache the resolved user for the process; forget them so the
    // next request re-resolves the (now deleted) token instead of reusing the cache.
    // Harmless if the framework already forgets guards between test requests.
    auth()->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json');
});

it('answers 204 without deleting anything for a transient token', function (): void {
    // Sanctum installs a TransientToken in cookie/SPA mode (the switch AGENTS.md
    // documents). It has no database row to revoke, and calling delete() on it would
    // be a fatal error — actingAs() is the only way to construct that request shape.
    $user = User::factory()->create();
    $user->createToken('untouched');

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/tokens/current')->assertNoContent();

    expect($user->tokens()->count())->toBe(1);
});

it('rejects revocation without a token', function (): void {
    $this->deleteJson('/api/v1/tokens/current')->assertStatus(401);
});
