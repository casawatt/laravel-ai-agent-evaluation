<?php

namespace Casawatt\LaravelAiAgentEvaluation;

class Price
{
    public function __construct(
        public readonly float $inputPerMillion,
        public readonly float $outputPerMillion,
    ) {}
}
