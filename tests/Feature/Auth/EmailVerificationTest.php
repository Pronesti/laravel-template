<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

// verificationPathFor() lives in tests/Pest.php so it is declared exactly once.

it('verifies an email address from the signed link in the notification', function (): void {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();
    expect($user->hasVerifiedEmail())->toBeFalse();

    $this->getJson(verificationPathFor($user))->assertNoContent();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->getAttribute('email_verified_at'))->not->toBeNull();

    Event::assertDispatched(Verified::class);
});

it('never puts the internal primary key in the verification link', function (): void {
    $user = User::factory()->unverified()->create();

    $path = verificationPathFor($user);

    expect($path)->toContain($user->getRouteKey())
        ->and($path)->not->toContain('/'.$user->id.'/');
});

it('rejects a verification link with a tampered signature', function (): void {
    $user = User::factory()->unverified()->create();

    $path = verificationPathFor($user);

    $this->getJson($path.'-tampered')
        ->assertForbidden()
        ->assertHeader('content-type', 'application/problem+json');

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a correctly signed link whose email hash does not match', function (): void {
    $user = User::factory()->unverified()->create();

    $path = verificationPathFor($user);

    // The signature covers the whole URL, so changing the hash also invalidates it —
    // proving the signature, not just the hash, gates the route.
    $this->getJson(str_replace(sha1($user->getEmailForVerification()), sha1('other@example.com'), $path))
        ->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification notification to a token authenticated user', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->withToken(tokenFor($user))
        ->postJson('/api/v1/email/verification-notification')
        ->assertNoContent();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend the notification to an already verified user', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->withToken(tokenFor($user))
        ->postJson('/api/v1/email/verification-notification')
        ->assertNoContent();

    Notification::assertNothingSent();
});

it('refuses to resend the verification notification without a token', function (): void {
    $this->postJson('/api/v1/email/verification-notification')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json');
});

it('exposes a verification notice route for the verified middleware', function (): void {
    expect(route('verification.notice', absolute: false))->toBe('/api/v1/email/verify');

    $this->getJson('/api/v1/email/verify')
        ->assertStatus(403)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJson(['status' => 403]);
});

it('does not explode when the verified middleware rejects a non-json request', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'verified'])
        ->get('/api/v1/_verified-probe', fn (): array => ['ok' => true]);

    $user = User::factory()->unverified()->create();

    // Accept: text/html is the path that resolves route('verification.notice'); without
    // that route registered this throws RouteNotFoundException instead of redirecting.
    $this->withToken(tokenFor($user))
        ->get('/api/v1/_verified-probe', ['Accept' => 'text/html'])
        ->assertRedirect('/api/v1/email/verify');
});

it('lets a verified user through the verified middleware', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'verified'])
        ->get('/api/v1/_verified-probe-ok', fn (): array => ['ok' => true]);

    $user = User::factory()->create();

    $this->withToken(tokenFor($user))
        ->getJson('/api/v1/_verified-probe-ok')
        ->assertOk();
});
