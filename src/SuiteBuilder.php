<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\Usage;

class SuiteBuilder
{
    /**
     * Build EvaluationSuite objects from storage result arrays.
     *
     * @param  array<string, array>  $results  Keyed by resultKey, values are serialized result data.
     * @return Collection<int, EvaluationSuite>
     */
    public static function fromStorageResults(array $results): Collection
    {
        $grouped = collect($results)->groupBy(fn (array $r) => $r['evaluation'] ?? 'unknown');

        return $grouped->map(function (Collection $results, string $evaluationName) {
            $suite = new EvaluationSuite($evaluationName, $results->first()['agent'] ?? '');

            foreach ($results as $result) {
                $suite->add(self::buildResult($result, $evaluationName));
            }

            return $suite;
        })->values();
    }

    public static function buildResult(array $data, string $evaluationName): EvaluationResult
    {
        $variant = new Variant(
            provider: $data['provider'] ?? 'unknown',
            model: $data['model'] ?? 'unknown',
        );
        $variant->label($data['variant'] ?? $variant->label);

        if (isset($data['instruction'])) {
            $variant->instruction($data['instruction']);
        }

        $assertionResults = null;

        if (isset($data['score']['assertions']) && is_array($data['score']['assertions'])) {
            $assertionResults = collect($data['score']['assertions'])->map(
                fn (array $a) => new AssertionResult(
                    assertion: $a['assertion'] ?? '',
                    passed: $a['passed'] ?? true,
                    weight: $a['weight'] ?? 1.0,
                    message: $a['message'] ?? null,
                ),
            );
        }

        return new EvaluationResult(
            evaluationName: $evaluationName,
            caseName: $data['case'] ?? '',
            caseDescription: $data['description'] ?? $data['case'] ?? '',
            variant: $variant,
            status: ResultStatus::from($data['status'] ?? 'passed'),
            failureMessage: $data['failure_message'] ?? null,
            skipReason: $data['skip_reason'] ?? null,
            latencySeconds: $data['latency_seconds'] ?? null,
            usage: isset($data['usage']) ? new Usage(
                promptTokens: $data['usage']['prompt_tokens'] ?? 0,
                completionTokens: $data['usage']['completion_tokens'] ?? 0,
            ) : null,
            responseText: $data['response_text'] ?? null,
            assertionResults: $assertionResults,
        );
    }
}
