<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a password reset link under the versioned prefix', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->postJson('/api/v1/forgot-password', ['email' => 'reset@example.com'])
        ->assertSuccessful();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('rejects a password reset request with no email as problem+json', function (): void {
    $this->postJson('/api/v1/forgot-password', [])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors']);
});

it('resets the password with the token from the notification', function (): void {
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSuccessful();

    expect(Hash::check('brand-new-password', (string) $user->refresh()->password))->toBeTrue();
});

it('does not accept password resets at the unversioned path', function (): void {
    $this->postJson('/forgot-password', ['email' => 'reset@example.com'])->assertNotFound();
});
