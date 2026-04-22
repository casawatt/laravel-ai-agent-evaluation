<?php

namespace Casawatt\LaravelAiAgentEvaluation;

readonly class WorkUnit
{
    public function __construct(
        public string $evaluationFile,
        public string $evaluationName,
        public string $methodName,
        public string $caseName,
        public string $caseDescription,
        public Variant $variant,
        public array $args,
        public ?string $dataSetLabel,
        public string $resultKey,
    ) {}
}
