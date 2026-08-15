<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('updates the name of a token authenticated user', function (): void {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'stable@example.com']);

    $this->withToken(tokenFor($user))
        ->putJson('/api/v1/user/profile-information', [
            'name' => 'New Name',
            'email' => 'stable@example.com',
        ])->assertNoContent();

    expect($user->refresh()->name)->toBe('New Name');
});

it('resets verification and re-notifies when the email changes', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'before@example.com']);
    expect($user->hasVerifiedEmail())->toBeTrue();

    $this->withToken(tokenFor($user))
        ->putJson('/api/v1/user/profile-information', [
            'name' => 'Ada Lovelace',
            'email' => 'after@example.com',
        ])->assertNoContent();

    $user->refresh();

    expect($user->email)->toBe('after@example.com')
        ->and($user->getAttribute('email_verified_at'))->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a profile update that collides with another email as problem+json', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $this->withToken(tokenFor($user))
        ->putJson('/api/v1/user/profile-information', [
            'name' => 'Ada Lovelace',
            'email' => 'taken@example.com',
        ])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors']);

    expect($user->refresh()->email)->toBe('mine@example.com');
});

it('allows taking an email address released by a soft deleted user', function (): void {
    User::factory()->create(['email' => 'released@example.com'])->delete();

    $user = User::factory()->create(['email' => 'current@example.com']);

    $this->withToken(tokenFor($user))
        ->putJson('/api/v1/user/profile-information', [
            'name' => 'Ada Lovelace',
            'email' => 'released@example.com',
        ])->assertNoContent();

    expect($user->refresh()->email)->toBe('released@example.com');
});

it('refuses a profile update without a token', function (): void {
    $this->putJson('/api/v1/user/profile-information', ['name' => 'X', 'email' => 'x@example.com'])
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json');
});

it('changes the password of a token authenticated user', function (): void {
    $user = User::factory()->create();

    $this->withToken(tokenFor($user))
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'new-password-value',
            'password_confirmation' => 'new-password-value',
        ])->assertNoContent();

    $user->refresh();

    expect(Hash::check('new-password-value', (string) $user->password))->toBeTrue()
        ->and(Hash::check('password', (string) $user->password))->toBeFalse();
});

it('lets the user obtain a new token with the changed password', function (): void {
    $user = User::factory()->create(['email' => 'rotate@example.com']);

    $this->withToken(tokenFor($user))
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'new-password-value',
            'password_confirmation' => 'new-password-value',
        ])->assertNoContent();

    $this->postJson('/api/v1/tokens', [
        'email' => 'rotate@example.com',
        'password' => 'new-password-value',
        'device_name' => 'test',
    ])->assertCreated();
});

it('rejects a password change with the wrong current password', function (): void {
    $user = User::factory()->create();

    $this->withToken(tokenFor($user))
        ->putJson('/api/v1/user/password', [
            'current_password' => 'not-the-password',
            'password' => 'new-password-value',
            'password_confirmation' => 'new-password-value',
        ])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('errors.current_password.0', 'The provided password does not match your current password.');

    expect(Hash::check('password', (string) $user->refresh()->password))->toBeTrue();
});

it('refuses a password change without a token', function (): void {
    $this->putJson('/api/v1/user/password', [])
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json');
});
