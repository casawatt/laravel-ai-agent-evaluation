<?php

namespace Casawatt\LaravelAiAgentEvaluation;

class AssertionResult
{
    public function __construct(
        public readonly string $assertion,
        public readonly bool $passed,
        public readonly float $weight,
        public readonly ?string $message = null,
        public readonly ?string $metric = null,
    ) {}
}
