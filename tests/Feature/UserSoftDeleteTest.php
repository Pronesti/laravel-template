<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('allows an email to be reused once the previous owner is soft deleted', function (): void {
    User::factory()->create(['email' => 'reuse@example.com'])->delete();

    $fresh = User::factory()->create(['email' => 'reuse@example.com']);

    expect($fresh->exists)->toBeTrue()
        ->and(User::withTrashed()->where('email', 'reuse@example.com')->count())->toBe(2);
});

it('still rejects two active users sharing an email', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    expect(fn () => User::factory()->create(['email' => 'taken@example.com']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('hides soft deleted users from ordinary queries', function (): void {
    User::factory()->create(['email' => 'gone@example.com'])->delete();

    expect(User::where('email', 'gone@example.com')->exists())->toBeFalse();
});
