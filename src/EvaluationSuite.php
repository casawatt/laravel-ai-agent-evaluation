<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Illuminate\Support\Collection;

class EvaluationSuite
{
    /** @var Collection<int, EvaluationResult> */
    public Collection $results;

    public function __construct(
        public readonly string $evaluationName,
        public readonly string $agentClass,
    ) {
        $this->results = new Collection;
    }

    public function add(EvaluationResult $result): void
    {
        $this->results->push($result);
    }

    public function providerSummaries(): Collection
    {
        return $this->results
            ->reject(fn (EvaluationResult $r) => $r->skipped())
            ->groupBy(fn (EvaluationResult $r) => $r->variantLabel())
            ->map(function (Collection $results) {
                $passed = $results->filter(fn (EvaluationResult $r) => $r->passed())->count();
                $total = $results->count();

                $totalWeight = (float) $results->sum(fn (EvaluationResult $r) => $r->totalWeight());
                $passedWeight = (float) $results->sum(fn (EvaluationResult $r) => $r->passedWeight());

                return [
                    'passed' => $passed,
                    'failed' => $total - $passed,
                    'total' => $total,
                    'pass_rate' => $total > 0 ? (float) ($passed / $total) : 0.0,
                    'avg_latency' => (float) ($results->avg('latencySeconds') ?? 0),
                    'total_latency' => (float) ($results->sum('latencySeconds') ?? 0),
                    'total_prompt_tokens' => (int) $results->sum(fn (EvaluationResult $r) => $r->usage->promptTokens ?? 0),
                    'total_completion_tokens' => (int) $results->sum(fn (EvaluationResult $r) => $r->usage->completionTokens ?? 0),
                    'total_weight' => $totalWeight,
                    'passed_weight' => $passedWeight,
                    'score' => $totalWeight > 0 ? (float) ($passedWeight / $totalWeight) : null,
                    'total_cost' => $results->contains(fn (EvaluationResult $r) => $r->cost() !== null)
                                    ? (float) $results->sum(fn (EvaluationResult $r) => $r->cost() ?? 0)
                                    : null,
                ];
            });
    }

    public function hasWeightedAssertions(): bool
    {
        return $this->results->contains(fn (EvaluationResult $r) => $r->hasWeightedAssertions());
    }

    public function hasPricing(): bool
    {
        return $this->results->contains(fn (EvaluationResult $r) => $r->variant->hasPricing());
    }

    public function totalPassed(): int
    {
        return $this->results->filter(fn (EvaluationResult $r) => $r->passed() && ! $r->reused)->count();
    }

    public function totalFailed(): int
    {
        return $this->results->filter(fn (EvaluationResult $r) => ($r->failed() || $r->errored()) && ! $r->reused)->count();
    }

    public function totalSkipped(): int
    {
        return $this->results->filter(fn (EvaluationResult $r) => $r->skipped())->count();
    }

    public function totalReused(): int
    {
        return $this->results->filter(fn (EvaluationResult $r) => $r->reused)->count();
    }

    public function allPassed(): bool
    {
        return $this->totalFailed() === 0;
    }
}
