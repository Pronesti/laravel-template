<?php

declare(strict_types=1);

namespace App\Http\Problem;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ProblemFactory
{
    private const string MODEL_NOT_FOUND_DETAIL = 'The requested resource does not exist.';

    /**
     * @return array{type: string, title: string, status: int, detail: string, errors?: array<string, list<string>>}|null
     */
    public static function make(Throwable $e, bool $debug): ?array
    {
        if ($e instanceof ApiProblem) {
            $message = $e->getMessage();

            return self::problem(
                $e->problemStatus(),
                $message !== '' ? $message : $e->problemTitle(),
                $e->problemTitle(),
                $e->problemType(),
            );
        }

        if ($e instanceof ValidationException) {
            /** @var array<string, list<string>> $errors */
            $errors = $e->errors();

            $detail = 'The given data was invalid.';

            foreach ($errors as $messages) {
                if ($messages !== []) {
                    $detail = $messages[0];

                    break;
                }
            }

            return self::problem(422, $detail, 'Unprocessable Content')
                + ['errors' => $errors];
        }

        if ($e instanceof AuthenticationException) {
            return self::problem(401, 'Unauthenticated.', 'Unauthorized');
        }

        // The next two branches are unreachable over HTTP: Laravel's
        // Handler::prepareException wraps both in an HttpException before any render
        // callback runs, so they arrive at the HttpExceptionInterface branch below. They
        // are retained because spec §5.1 requires this factory to map both explicitly,
        // and because it is a plain static mapper that unit tests and future callers
        // (queued jobs, console commands) can hand a raw exception to.
        if ($e instanceof AuthorizationException) {
            return self::problem(403, $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.', 'Forbidden');
        }

        if ($e instanceof ModelNotFoundException) {
            return self::problem(404, self::MODEL_NOT_FOUND_DETAIL, 'Not Found');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            // ModelNotFoundException::getMessage() names the model FQCN and the key it
            // looked for ("No query results for model [App\Models\User] 0193…"). That
            // text survives into NotFoundHttpException, so it must not be echoed back.
            if ($e->getPrevious() instanceof ModelNotFoundException) {
                return self::problem(404, self::MODEL_NOT_FOUND_DETAIL, 'Not Found');
            }

            return self::problem($status, $e->getMessage() !== '' ? $e->getMessage() : self::title($status), self::title($status));
        }

        if ($debug) {
            return null;
        }

        return self::problem(500, 'An unexpected error occurred.', 'Internal Server Error');
    }

    /**
     * @return array{type: string, title: string, status: int, detail: string}
     */
    private static function problem(int $status, string $detail, string $title, string $type = 'about:blank'): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    private static function title(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'Error';
    }
}
