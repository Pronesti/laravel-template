<?php

declare(strict_types=1);

namespace App\Support\Scramble;

use App\Http\Problem\ProblemFactory;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Replaces Scramble's default error-response schema (`{message, errors}`)
 * with the RFC 9457 problem+json shape that {@see ProblemFactory}
 * actually renders, on every documented response with a 4xx/5xx status code.
 */
final class ProblemResponseExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        foreach ($operation->responses ?? [] as $response) {
            $this->documentAsProblem($response instanceof Reference ? $response->resolve() : $response);
        }
    }

    private function documentAsProblem(mixed $response): void
    {
        if (! $response instanceof Response || ! is_int($response->code) || $response->code < 400) {
            return;
        }

        $response->content = [];
        $response->setContent('application/problem+json', $this->problemSchema());
    }

    private function problemSchema(): Schema
    {
        $problem = new ObjectType;
        $problem->addProperty('type', new StringType);
        $problem->addProperty('title', new StringType);
        $problem->addProperty('status', new IntegerType);
        $problem->addProperty('detail', new StringType);
        $problem->setRequired(['type', 'title', 'status', 'detail']);

        // Schema::fromType() has no return type declaration upstream; it always
        // constructs and returns a Schema, per its own source.
        /** @var Schema $schema */
        $schema = Schema::fromType($problem);

        return $schema;
    }
}
