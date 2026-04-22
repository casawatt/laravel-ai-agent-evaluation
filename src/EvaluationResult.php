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

    public function cost(): ?float
    {
        if ($this->usage === null || ! $this->variant->hasPricing()) {
            return null;
        }

        return ($this->usage->promptTokens * $this->variant->price->inputPerMillion
            + $this->usage->completionTokens * $this->variant->price->outputPerMillion) / 1_000_000;
    }

    public function toStorageArray(): array
    {
        return [
            'evaluation' => $this->evaluationName,
            'case' => $this->caseName,
            'description' => $this->caseDescription,
            'variant' => $this->variantLabel(),
            'provider' => $this->variant->providerValue(),
            'model' => $this->variant->model,
            'instruction' => $this->variant->instruction,
            'input_cost_per_million' => $this->variant->price?->inputPerMillion,
            'output_cost_per_million' => $this->variant->price?->outputPerMillion,
            'status' => $this->status->value,
            'failure_message' => $this->failureMessage,
            'skip_reason' => $this->skipReason,
            'latency_seconds' => $this->latencySeconds,
            'usage' => $this->usage?->toArray(),
            'response_text' => $this->responseText,
            'score' => $this->hasWeightedAssertions() ? [
                'passed_weight' => $this->passedWeight(),
                'total_weight' => $this->totalWeight(),
                'assertions' => $this->assertionResults?->map(fn ($a) => [
                    'assertion' => $a->assertion,
                    'passed' => $a->passed,
                    'weight' => $a->weight,
                    'message' => $a->message,
                ])->all(),
            ] : null,
        ];
    }

    public function withoutException(): self
    {
        if ($this->exception === null) {
            return $this;
        }

        return new self(
            evaluationName: $this->evaluationName,
            caseName: $this->caseName,
            caseDescription: $this->caseDescription,
            variant: $this->variant,
            status: $this->status,
            failureMessage: $this->failureMessage,
            skipReason: $this->skipReason,
            latencySeconds: $this->latencySeconds,
            usage: $this->usage,
            responseText: $this->responseText,
            assertionResults: $this->assertionResults,
            reused: $this->reused,
        );
    }
}
