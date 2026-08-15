<?php

declare(strict_types=1);

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('updates the password without consulting any guard', function (): void {
    $user = User::factory()->create(['password' => 'old-password-value']);

    // No actingAs, no token: nothing is authenticated. The action must still work, because
    // the password it verifies belongs to the $user it was handed.
    (new UpdateUserPassword)->update($user, [
        'current_password' => 'old-password-value',
        'password' => 'new-password-value',
        'password_confirmation' => 'new-password-value',
    ]);

    expect(Hash::check('new-password-value', (string) $user->refresh()->password))->toBeTrue();
});

it('rejects a wrong current password without consulting any guard', function (): void {
    $user = User::factory()->create(['password' => 'old-password-value']);

    expect(fn () => (new UpdateUserPassword)->update($user, [
        'current_password' => 'not-the-password',
        'password' => 'new-password-value',
        'password_confirmation' => 'new-password-value',
    ]))->toThrow(ValidationException::class);

    expect(Hash::check('old-password-value', (string) $user->refresh()->password))->toBeTrue();
});
