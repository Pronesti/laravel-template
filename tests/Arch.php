<?php

declare(strict_types=1);

// 'die' and 'exit' are deliberately absent: they are language constructs, not functions,
// so Pest's toBeUsed() cannot see them and listing them would be a silent no-op.
arch('no debugging helpers ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'var_export'])
    ->not->toBeUsed();

arch('everything is strictly typed')
    ->expect('App')
    ->toUseStrictTypes();

arch('models are final')
    ->expect('App\Models')
    ->classes()
    ->toBeFinal();

arch('controllers are suffixed and confined')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

// The real boundary: models are reachable from the layers that legitimately persist or
// serialize them, and from nowhere else. In particular App\Http\Problem must stay
// model-agnostic — the RFC 9457 mapper maps exceptions, it does not know about domain
// objects — and App\Support must not reach into persistence. App\Http\Auth is the one
// non-controller HTTP layer allowed in: it narrows the authenticated request to a typed User.
arch('models are only used by the layers allowed to touch them')
    ->expect('App\Models')
    ->toOnlyBeUsedIn([
        'App\Actions',
        'App\Http\Auth',
        'App\Http\Controllers',
        'App\Http\Resources',
        'App\Models',
        'App\Providers',
        'Database\Factories',
        'Database\Seeders',
        'Tests',
    ]);

// The problem+json mapper is a pure exception -> array function. If it ever needs the
// framework's container, resources or models, the abstraction has leaked.
arch('the problem mapper stays free of framework state')
    ->expect('App\Http\Problem')
    ->not->toUse(['App\Models', 'Illuminate\Http', 'Illuminate\Support\Facades']);
