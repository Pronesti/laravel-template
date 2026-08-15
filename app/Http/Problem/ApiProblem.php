<?php

declare(strict_types=1);

namespace App\Http\Problem;

interface ApiProblem
{
    /** An absolute URI identifying the problem type, or "about:blank". */
    public function problemType(): string;

    public function problemTitle(): string;

    public function problemStatus(): int;
}
