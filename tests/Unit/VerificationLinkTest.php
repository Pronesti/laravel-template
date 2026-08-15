<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\VerificationLink;

it('signs a relative path keyed on the external id and the email hash', function (): void {
    $user = User::factory()->unverified()->create();

    $path = VerificationLink::signedPath($user);

    expect($path)->toStartWith('/api/v1/email/verify/'.$user->external_id.'/'.sha1($user->getEmailForVerification()))
        ->and($path)->toContain('signature=')
        ->and($path)->not->toContain('/'.$user->id.'/');
});

it('prefixes the minted link with the frontend url', function (): void {
    $this->freezeTime();
    config()->set('app.frontend_url', 'https://app.example.test/');

    $user = User::factory()->unverified()->create();

    expect(VerificationLink::mint($user))
        ->toBe('https://app.example.test'.VerificationLink::signedPath($user));
});

it('matches only the hash of the user email', function (): void {
    $user = User::factory()->unverified()->create();

    expect(VerificationLink::matches($user, sha1($user->getEmailForVerification())))->toBeTrue()
        ->and(VerificationLink::matches($user, sha1('other@example.com')))->toBeFalse();
});
