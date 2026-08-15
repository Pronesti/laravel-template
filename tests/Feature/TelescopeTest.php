<?php

declare(strict_types=1);

use Laravel\Telescope\TelescopeServiceProvider;

it('has telescope installed but unrouted outside local', function (): void {
    // Both halves matter. The class check proves Telescope IS installed, so the 404 below
    // can only mean the environment gate did its job — not that the package is absent.
    expect(class_exists(TelescopeServiceProvider::class))->toBeTrue()
        ->and(app()->environment())->toBe('testing');

    $this->get('/telescope')->assertNotFound();
});

it('ships no telescope migrations', function (): void {
    expect(glob(database_path('migrations/*telescope*')))->toBeEmpty();
});
