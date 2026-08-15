<?php

declare(strict_types=1);

use App\Models\User;

it('returns the authenticated user', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $this->withToken(tokenFor($user))->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson([
            'id' => $user->external_id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
});

it('never exposes the internal primary key', function (): void {
    $user = User::factory()->create();

    /** @var array<string, mixed> $body */
    $body = $this->withToken(tokenFor($user))->getJson('/api/v1/me')->json();

    expect($body['id'])->toBe($user->external_id)
        ->and($body['id'])->not->toBe((string) $user->id)
        ->and($body)->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes']);
});

it('rejects unauthenticated access as problem+json', function (): void {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertHeader('content-type', 'application/problem+json');
});
