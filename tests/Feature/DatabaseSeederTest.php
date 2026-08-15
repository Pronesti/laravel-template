<?php

declare(strict_types=1);

use App\Models\User;

it('seeds a user with a populated external id', function (): void {
    $this->seed();

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->getAttribute('external_id'))->toBeString()->not->toBeEmpty();
});
