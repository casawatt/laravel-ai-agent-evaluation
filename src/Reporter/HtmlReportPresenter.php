<?php

namespace Casawatt\LaravelAiAgentEvaluation\Reporter;

class HtmlReportPresenter
{
    /**
     * @param  array<string, array<string, mixed>>  $rows  raw storage rows keyed by result key
     * @return array<int, array{
     *     name: string,
     *     cases: array<int, string>,
     *     variants: array<int, string>,
     *     matrix: array<string, array<string, array<string, mixed>>>,
     *     totals: array<string, array{
     *         passed: int,
     *         failed: int,
     *         errored: int,
     *         skipped: int,
     *         avg_latency: float|null,
     *         sum_tokens: int,
     *         sum_cost: float|null,
     *         avg_score_percent: float|null,
     *         metrics: array<string, float>
     *     }>
     * }>
     */
    public function present(array $rows): array
    {
        $byEvaluation = [];

        foreach ($rows as $row) {
            $evaluation = (string) ($row['evaluation'] ?? 'Unknown');
            $byEvaluation[$evaluation][] = $row;
        }

        $output = [];

        foreach ($byEvaluation as $name => $evaluationRows) {
            $cases = [];
            $variants = [];
            $matrix = [];

            foreach ($evaluationRows as $row) {
                $case = (string) ($row['case'] ?? '');
                $variant = (string) ($row['variant'] ?? '');

                if (! in_array($case, $cases, true)) {
                    $cases[] = $case;
                }
                if (! in_array($variant, $variants, true)) {
                    $variants[] = $variant;
                }

                $matrix[$case][$variant] = $row;
            }

            $output[] = [
                'name' => $name,
                'cases' => $cases,
                'variants' => $variants,
                'matrix' => $matrix,
                'totals' => $this->computeTotals($evaluationRows, $variants),
            ];
        }

        return $output;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $variants
     * @return array<string, array{
     *     passed: int,
     *     failed: int,
     *     errored: int,
     *     skipped: int,
     *     avg_latency: float|null,
     *     sum_tokens: int,
     *     sum_cost: float|null,
     *     avg_score_percent: float|null,
     *     metrics: array<string, float>
     * }>
     */
    private function computeTotals(array $rows, array $variants): array
    {
        $totals = [];

        foreach ($variants as $variant) {
            $variantRows = array_filter($rows, fn (array $r) => ($r['variant'] ?? null) === $variant);

            $passed = 0;
            $failed = 0;
            $errored = 0;
            $skipped = 0;
            $latencies = [];
            $tokens = 0;
            $costs = [];
            $scorePercents = [];
            /** @var array<string, array{passed: float, total: float}> $metricBuckets */
            $metricBuckets = [];

            foreach ($variantRows as $row) {
                match ($row['status'] ?? null) {
                    'passed' => $passed++,
                    'failed' => $failed++,
                    'error' => $errored++,
                    'skipped' => $skipped++,
                    default => null,
                };

                if (isset($row['latency_seconds']) && is_numeric($row['latency_seconds'])) {
                    $latencies[] = (float) $row['latency_seconds'];
                }

                if (isset($row['usage']) && is_array($row['usage'])) {
                    $tokens += (int) ($row['usage']['prompt_tokens'] ?? 0);
                    $tokens += (int) ($row['usage']['completion_tokens'] ?? 0);
                }

                if (isset($row['cost']) && is_numeric($row['cost'])) {
                    $costs[] = (float) $row['cost'];
                }

                $score = $row['score'] ?? null;
                if (is_array($score) && ($score['total_weight'] ?? 0) > 0) {
                    $scorePercents[] = ((float) $score['passed_weight']) / ((float) $score['total_weight']) * 100;
                }

                if (is_array($score) && is_array($score['assertions'] ?? null)) {
                    foreach ($score['assertions'] as $assertion) {
                        $metric = $assertion['metric'] ?? null;
                        if (! is_string($metric) || $metric === '') {
                            continue;
                        }

                        $weight = (float) ($assertion['weight'] ?? 0);
                        $metricBuckets[$metric] ??= ['passed' => 0.0, 'total' => 0.0];
                        $metricBuckets[$metric]['total'] += $weight;

                        if (($assertion['passed'] ?? false) === true) {
                            $metricBuckets[$metric]['passed'] += $weight;
                        }
                    }
                }
            }

            $metrics = [];
            foreach ($metricBuckets as $metric => $bucket) {
                if ($bucket['total'] > 0) {
                    $metrics[$metric] = $bucket['passed'] / $bucket['total'] * 100;
                }
            }
            ksort($metrics);

            $totals[$variant] = [
                'passed' => $passed,
                'failed' => $failed,
                'errored' => $errored,
                'skipped' => $skipped,
                'avg_latency' => $latencies === [] ? null : array_sum($latencies) / count($latencies),
                'sum_tokens' => $tokens,
                'sum_cost' => $costs === [] ? null : array_sum($costs),
                'avg_score_percent' => $scorePercents === [] ? null : array_sum($scorePercents) / count($scorePercents),
                'metrics' => $metrics,
            ];
        }

        return $totals;
    }
}
