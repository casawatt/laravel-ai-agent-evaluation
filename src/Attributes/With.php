<?php

namespace Casawatt\LaravelAiAgentEvaluation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class With
{
    public function __construct(
        public readonly string $method,
    ) {}
}
