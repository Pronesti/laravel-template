<?php

declare(strict_types=1);

use App\Http\Problem\ApiProblem;
use App\Http\Problem\ProblemFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('maps validation failures to 422 with an errors member', function (): void {
    $exception = ValidationException::withMessages(['email' => ['The email field is required.']]);

    $problem = ProblemFactory::make($exception, debug: false);
    assert($problem !== null);
    assert(array_key_exists('errors', $problem));

    expect($problem)->toMatchArray([
        'type' => 'about:blank',
        'status' => 422,
        'detail' => 'The email field is required.',
    ])->and($problem['errors'])->toBe(['email' => ['The email field is required.']]);
});

it('maps authentication failures to 401', function (): void {
    expect(ProblemFactory::make(new AuthenticationException, debug: false))
        ->toMatchArray(['status' => 401, 'title' => 'Unauthorized']);
});

it('maps authorization failures to 403', function (): void {
    expect(ProblemFactory::make(new AuthorizationException, debug: false))
        ->toMatchArray(['status' => 403, 'title' => 'Forbidden']);
});

it('maps missing models and routes to 404', function (): void {
    expect(ProblemFactory::make(new ModelNotFoundException, debug: false))
        ->toMatchArray(['status' => 404])
        ->and(ProblemFactory::make(new NotFoundHttpException, debug: false))
        ->toMatchArray(['status' => 404]);
});

it('never leaks internal messages on unhandled exceptions in production', function (): void {
    $problem = ProblemFactory::make(new RuntimeException('connection string secret'), debug: false);
    assert($problem !== null);

    expect($problem)->toMatchArray(['status' => 500, 'title' => 'Internal Server Error'])
        ->and($problem['detail'])->not->toContain('secret');
});

it('falls through to laravel rendering for unhandled exceptions in debug', function (): void {
    expect(ProblemFactory::make(new RuntimeException('boom'), debug: true))->toBeNull();
});

it('lets an exception describe its own problem type', function (): void {
    $exception = new class('The flux capacitor is offline.') extends RuntimeException implements ApiProblem
    {
        public function problemType(): string
        {
            return 'https://example.com/problems/flux-capacitor';
        }

        public function problemTitle(): string
        {
            return 'Flux Capacitor Offline';
        }

        public function problemStatus(): int
        {
            return 503;
        }
    };

    expect(ProblemFactory::make($exception, debug: false))->toBe([
        'type' => 'https://example.com/problems/flux-capacitor',
        'title' => 'Flux Capacitor Offline',
        'status' => 503,
        'detail' => 'The flux capacitor is offline.',
    ]);
});

it('falls back to the problem title when an ApiProblem carries no message', function (): void {
    $exception = new class extends RuntimeException implements ApiProblem
    {
        public function problemType(): string
        {
            return 'https://example.com/problems/flux-capacitor';
        }

        public function problemTitle(): string
        {
            return 'Flux Capacitor Offline';
        }

        public function problemStatus(): int
        {
            return 503;
        }
    };

    // Mirrors the HttpExceptionInterface branch: a message-less exception must not
    // emit "detail": "" — RFC 9457's detail is a human-readable explanation.
    expect(ProblemFactory::make($exception, debug: false))
        ->toMatchArray(['detail' => 'Flux Capacitor Offline']);
});
