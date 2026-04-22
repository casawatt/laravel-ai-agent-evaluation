<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\Usage;

class EvaluationResult
{
    public function __construct(
        public readonly string $evaluationName,
        public readonly string $caseName,
        public readonly string $caseDescription,
        public readonly Variant $variant,
        public readonly ResultStatus $status,
        public readonly ?string $failureMessage = null,
        public readonly ?string $skipReason = null,
        public readonly ?float $latencySeconds = null,
        public readonly ?Usage $usage = null,
        public readonly ?string $responseText = null,
        public readonly ?\Throwable $exception = null,
        /** @var Collection<int, AssertionResult>|null */
        public readonly ?Collection $assertionResults = null,
        public readonly bool $reused = false,
    ) {}

    public function resultKey(): string
    {
        return $this->evaluationName.'::'.$this->caseName.'::'.$this->variantLabel();
    }

    public function passed(): bool
    {
        return $this->status === ResultStatus::Passed;
    }

    public function failed(): bool
    {
        return $this->status === ResultStatus::Failed;
    }

    public function skipped(): bool
    {
        return $this->status === ResultStatus::Skipped;
    }

    public function errored(): bool
    {
        return $this->status === ResultStatus::Error;
    }

    public function variantLabel(): string
    {
        return $this->variant->label;
    }

    public function totalWeight(): float
    {
        return $this->assertionResults?->sum('weight') ?? 0;
    }

    public function passedWeight(): float
    {
        return $this->assertionResults?->where('passed', true)->sum('weight') ?? 0;
    }

    public function hasWeightedAssertions(): bool
    {
        return $this->totalWeight() > 0;
    }
}
