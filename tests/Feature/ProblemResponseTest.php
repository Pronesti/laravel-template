<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

it('renders unknown api routes as problem+json', function (): void {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJson(['type' => 'about:blank', 'status' => 404, 'title' => 'Not Found']);
});

it('renders validation failures with an errors member', function (): void {
    Route::middleware('api')->post('/api/v1/_validation-probe', function (): void {
        throw ValidationException::withMessages(['email' => ['The email field is required.']]);
    });

    $this->postJson('/api/v1/_validation-probe')
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJson([
            'status' => 422,
            'errors' => ['email' => ['The email field is required.']],
        ]);
});

it('renders unauthenticated access as problem+json', function (): void {
    Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/_auth-probe', fn (): array => []);

    $this->getJson('/api/v1/_auth-probe')
        ->assertStatus(401)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJson(['status' => 401, 'title' => 'Unauthorized']);
});

it('renders authorization failures as problem+json', function (): void {
    Route::middleware('api')->get('/api/v1/_forbidden-probe', function (): void {
        throw new AuthorizationException('This action is unauthorized.');
    });

    $this->getJson('/api/v1/_forbidden-probe')
        ->assertStatus(403)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail'])
        ->assertJson([
            'type' => 'about:blank',
            'status' => 403,
            'title' => 'Forbidden',
            'detail' => 'This action is unauthorized.',
        ]);
});

it('renders unhandled exceptions as an opaque 500 problem when debug is off', function (): void {
    // The only branch whose behaviour depends on the debug flag, and phpunit.xml sets no
    // APP_DEBUG — so it is pinned here rather than left to the ambient environment.
    config()->set('app.debug', false);

    Route::middleware('api')->get('/api/v1/_error-probe', function (): void {
        throw new RuntimeException('pgsql://user:hunter2@db.internal/app');
    });

    $response = $this->getJson('/api/v1/_error-probe')
        ->assertStatus(500)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail'])
        ->assertJson([
            'type' => 'about:blank',
            'status' => 500,
            'title' => 'Internal Server Error',
            'detail' => 'An unexpected error occurred.',
        ]);

    expect($response->content())->not->toContain('hunter2')
        ->and($response->content())->not->toContain('RuntimeException');
});

it('does not leak the model class name when route model binding misses', function (): void {
    Route::middleware('api')->get('/api/v1/_binding-probe/{user}', fn (User $user): array => []);

    $response = $this->getJson('/api/v1/_binding-probe/019000ff-0000-7000-8000-000000000000')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJson([
            'status' => 404,
            'title' => 'Not Found',
            'detail' => 'The requested resource does not exist.',
        ]);

    expect($response->content())->not->toContain(User::class)
        ->and($response->content())->not->toContain('No query results');
});

it('resolves a route model binding on external_id, never the primary key', function (): void {
    Route::middleware('api')->get('/api/v1/_binding-probe/{user}', fn (User $user): array => ['ok' => $user->email]);

    $user = User::factory()->create();

    $this->getJson('/api/v1/_binding-probe/'.$user->external_id)->assertOk();
    $this->getJson('/api/v1/_binding-probe/'.$user->id)->assertNotFound();
});
