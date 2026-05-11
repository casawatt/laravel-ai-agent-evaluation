<?php

namespace Casawatt\LaravelAiAgentEvaluation;

readonly class Price
{
    public function __construct(
        public float $inputPerMillion,
        public float $outputPerMillion,
    ) {}
}
