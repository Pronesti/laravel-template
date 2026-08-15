<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\VerificationLink;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('points verification links at the frontend', function (): void {
    config()->set('app.frontend_url', 'https://app.example.test');
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with((string) $url, 'https://app.example.test/');
    });
});

it('mails exactly the link VerificationLink mints', function (): void {
    $this->freezeTime();
    config()->set('app.frontend_url', 'https://app.example.test');
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, fn (VerifyEmail $notification): bool => (string) $notification->toMail($user)->actionUrl === VerificationLink::mint($user));
});

it('points password reset links at the frontend', function (): void {
    config()->set('app.frontend_url', 'https://app.example.test');
    Notification::fake();

    $user = User::factory()->create();
    $user->sendPasswordResetNotification('test-token');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with((string) $url, 'https://app.example.test/reset-password/test-token');
    });
});
