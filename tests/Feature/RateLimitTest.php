<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('throttles the api group at 60 requests per minute', function (): void {
    Route::middleware('api')->get('/api/v1/_throttle-probe', fn (): array => ['ok' => true]);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/_throttle-probe')->assertOk();
    }

    $this->getJson('/api/v1/_throttle-probe')->assertStatus(429);
});

it('keeps the exception headers on a throttled problem+json response', function (): void {
    // The problem+json renderer builds a fresh JsonResponse; if it does not merge
    // HttpExceptionInterface::getHeaders() the rate limiter becomes undiscoverable.
    Route::middleware('api')->get('/api/v1/_retry-after-probe', fn (): array => ['ok' => true]);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/_retry-after-probe')->assertOk();
    }

    $response = $this->getJson('/api/v1/_retry-after-probe')
        ->assertStatus(429)
        ->assertHeader('content-type', 'application/problem+json');

    $response->assertHeader('Retry-After');
    $response->assertHeader('X-RateLimit-Limit', 60);

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
});

it('keeps the Allow header on a 405 problem+json response', function (): void {
    Route::middleware('api')->get('/api/v1/_method-probe', fn (): array => ['ok' => true]);

    $this->postJson('/api/v1/_method-probe')
        ->assertStatus(405)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertHeader('Allow', 'GET, HEAD');
});

it('gives each token user an independent 60-per-minute bucket', function (): void {
    Route::middleware('api')->get('/api/v1/_user-bucket-probe', fn (): array => ['ok' => true]);

    $first = User::factory()->create();
    $second = User::factory()->create();

    $firstToken = tokenFor($first);

    for ($i = 0; $i < 60; $i++) {
        $this->withToken($firstToken)->getJson('/api/v1/_user-bucket-probe')->assertOk();
    }

    $this->withToken($firstToken)->getJson('/api/v1/_user-bucket-probe')->assertStatus(429);

    // Guard instances can cache the resolved user for the process; forget them so
    // the next request authenticates as the second user, not a cached first.
    auth()->forgetGuards();

    // Same IP, different user: must not inherit the exhausted bucket. Before the
    // limiter resolved the sanctum guard explicitly, every bearer request fell
    // through to the shared IP key and this request answered 429.
    $this->withToken(tokenFor($second))->getJson('/api/v1/_user-bucket-probe')->assertOk();
});
