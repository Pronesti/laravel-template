<?php

declare(strict_types=1);

use App\Models\User;

it('registers a user under the versioned prefix', function (): void {
    $this->postJson('/api/v1/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password-password',
        'password_confirmation' => 'password-password',
    ])->assertSuccessful();

    expect(User::where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('rejects invalid registrations as problem+json', function (): void {
    $this->postJson('/api/v1/register', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors']);
});

it('lets a soft deleted user re-register with the same email', function (): void {
    User::factory()->create(['email' => 'returning@example.com'])->delete();

    $this->postJson('/api/v1/register', [
        'name' => 'Returning User',
        'email' => 'returning@example.com',
        'password' => 'password-password',
        'password_confirmation' => 'password-password',
    ])->assertSuccessful();
});

it('rejects a registration for an email an active user already holds', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/register', [
        'name' => 'Impostor',
        'email' => 'taken@example.com',
        'password' => 'password-password',
        'password_confirmation' => 'password-password',
    ])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('errors.email.0', 'The email has already been taken.');

    expect(User::where('email', 'taken@example.com')->count())->toBe(1);
});

it('does not register at the unversioned path', function (): void {
    $this->postJson('/register', [])->assertNotFound();
});
