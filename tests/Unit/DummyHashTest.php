<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\IssueTokenController;

it('keeps the dummy hash aligned with the shipped hashing configuration', function (): void {
    $constant = new ReflectionClassConstant(IssueTokenController::class, 'DUMMY_HASH');
    $dummyHash = $constant->getValue();
    assert(is_string($dummyHash));

    // .env.example is what a fresh project runs with. The dummy comparison only
    // costs the same as a genuine wrong-password check if its algorithm and cost
    // match that default — if this test fails, regenerate the constant:
    //   php -r "echo password_hash('dummy', PASSWORD_BCRYPT, ['cost' => <rounds>]);"
    $env = (string) file_get_contents(base_path('.env.example'));
    $shippedRounds = preg_match('/^BCRYPT_ROUNDS=(\d+)$/m', $env, $matches) === 1
        ? (int) $matches[1]
        : null;

    expect($shippedRounds)->not->toBeNull()
        ->and(config('hashing.driver'))->toBe('bcrypt')
        ->and(password_needs_rehash($dummyHash, PASSWORD_BCRYPT, ['cost' => $shippedRounds]))->toBeFalse();
});
