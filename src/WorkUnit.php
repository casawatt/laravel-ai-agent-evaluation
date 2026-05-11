<?php

namespace Casawatt\LaravelAiAgentEvaluation;

class WorkUnit
{
    public function __construct(
        public readonly string $evaluationFile,
        public readonly string $evaluationName,
        public readonly string $methodName,
        public readonly string $caseName,
        public readonly string $caseDescription,
        public readonly Variant $variant,
        public readonly array $args,
        public readonly ?string $dataSetLabel,
        public readonly string $resultKey,
    ) {}
}
