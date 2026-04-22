<?php

namespace Casawatt\LaravelAiAgentEvaluation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class EvaluationCase
{
    public function __construct(
        public ?string $description = null,
    ) {}
}
