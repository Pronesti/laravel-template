<?php

declare(strict_types=1);

it('serves api docs in local', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    $this->get('/docs/api')->assertSuccessful();
});

it('hides api docs outside local', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/docs/api')->assertForbidden();
});

it('documents the bearer scheme and marks the token routes as protected', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    $this->getJson('/docs/api.json')
        ->assertSuccessful()
        ->assertJsonPath('components.securitySchemes.http.type', 'http')
        ->assertJsonPath('components.securitySchemes.http.scheme', 'bearer')
        // The bearer requirement applies globally...
        ->assertJsonPath('security', [['http' => []]])
        // ...auth:sanctum routes inherit it by carrying no per-operation override...
        ->assertJsonMissingPath('paths./v1/me.get.security')
        ->assertJsonMissingPath('paths./v1/user/password.put.security')
        // ...and the unauthenticated token endpoint opts out explicitly.
        ->assertJsonPath('paths./v1/tokens.post.security', [])
        ->assertJsonPath('paths./v1/register.post.security', []);
});
