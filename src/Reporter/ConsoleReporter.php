<?php

namespace Casawatt\LaravelAiAgentEvaluation\Reporter;

use Casawatt\LaravelAiAgentEvaluation\EvaluationResult;
use Casawatt\LaravelAiAgentEvaluation\EvaluationSuite;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleReporter
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    /**
     * @param  Collection<int, EvaluationSuite>  $suites
     */
    public function render(Collection $suites): void
    {
        foreach ($suites as $suite) {
            $this->renderTestMatrix($suite);
            $this->output->writeln('');
            $this->renderProviderSummary($suite);
            $this->output->writeln('');
        }
    }

    private function renderTestMatrix(EvaluationSuite $suite): void
    {
        $this->output->writeln("<info>{$suite->evaluationName}</info>");
        $this->output->writeln('');

        $providers = $suite->results
            ->map(fn (EvaluationResult $r) => $r->variantLabel())
            ->unique()
            ->values();

        $cases = $suite->results
            ->map(fn (EvaluationResult $r) => $r->caseName)
            ->unique()
            ->values();

        $table = new Table($this->output);

        $headers = ['Case'];
        foreach ($providers as $provider) {
            $headers[] = $this->shortenProviderLabel($provider);
        }
        $table->setHeaders($headers);

        foreach ($cases as $caseName) {
            $row = [$caseName];

            foreach ($providers as $provider) {
                $result = $suite->results->first(
                    fn (EvaluationResult $r) => $r->caseName === $caseName && $r->variantLabel() === $provider,
                );

                if ($result === null) {
                    $row[] = '-';

                    continue;
                }

                if ($result->skipped()) {
                    $row[] = '<fg=yellow>SKIP</>';

                    continue;
                }

                $status = match (true) {
                    $result->passed() => '<fg=green>PASS</>',
                    $result->errored() => '<fg=red>ERROR</>',
                    default => '<fg=red>FAIL</>',
                };

                $latency = $result->latencySeconds !== null
                    ? $this->formatLatency($result->latencySeconds)
                    : '-';

                $tokens = $result->usage !== null
                    ? ($result->usage->promptTokens + $result->usage->completionTokens).'tok'
                    : '-';

                $score = $result->hasWeightedAssertions()
                    ? ' '.number_format($result->passedWeight() / $result->totalWeight() * 100).'%'
                    : '';

                $row[] = "{$status} {$latency} {$tokens}{$score}";
            }

            $table->addRow($row);
        }

        $table->render();
    }

    private function renderProviderSummary(EvaluationSuite $suite): void
    {
        $hasWeights = $suite->hasWeightedAssertions();

        $table = new Table($this->output);

        $headers = ['Variant', 'Results'];
        if ($hasWeights) {
            $headers[] = 'Score';
        }
        $headers = [...$headers, 'Avg Latency', 'Tokens In', 'Tokens Out'];
        $table->setHeaders($headers);

        $summaries = $suite->providerSummaries();

        $totalPassed = 0;
        $totalTests = 0;
        $totalLatency = 0;
        $totalPromptTokens = 0;
        $totalCompletionTokens = 0;
        $totalWeightSum = 0;
        $totalPassedWeightSum = 0;
        $providerCount = 0;

        foreach ($summaries as $label => $summary) {
            $passRate = number_format($summary['pass_rate'] * 100, 1);
            $results = "{$summary['passed']} / {$summary['total']} ({$passRate}%)";
            $latency = $this->formatLatency($summary['avg_latency']);

            $row = [
                $this->shortenProviderLabel($label),
                $results,
            ];

            if ($hasWeights) {
                $score = $summary['score'] !== null
                    ? "{$summary['passed_weight']} / {$summary['total_weight']} (".number_format($summary['score'] * 100, 1).'%)'
                    : '-';
                $row[] = $score;
            }

            $row = [...$row, $latency, number_format($summary['total_prompt_tokens']), number_format($summary['total_completion_tokens'])];
            $table->addRow($row);

            $totalPassed += $summary['passed'];
            $totalTests += $summary['total'];
            $totalLatency += $summary['avg_latency'];
            $totalPromptTokens += $summary['total_prompt_tokens'];
            $totalCompletionTokens += $summary['total_completion_tokens'];
            $totalWeightSum += $summary['total_weight'];
            $totalPassedWeightSum += $summary['passed_weight'];
            $providerCount++;
        }

        $table->addRow(new TableSeparator);

        $overallPassRate = $totalTests > 0 ? number_format(($totalPassed / $totalTests) * 100, 1) : '0.0';
        $avgLatency = $providerCount > 0 ? $this->formatLatency($totalLatency / $providerCount) : '-';

        $summaryRow = [
            '<options=bold>Summary</>',
            "<options=bold>{$totalPassed} / {$totalTests} ({$overallPassRate}%)</>",
        ];

        if ($hasWeights) {
            $overallScore = $totalWeightSum > 0
                ? number_format($totalPassedWeightSum / $totalWeightSum * 100, 1)
                : '0.0';
            $summaryRow[] = "<options=bold>{$totalPassedWeightSum} / {$totalWeightSum} ({$overallScore}%)</>";
        }

        $summaryRow = [
            ...$summaryRow,
            "<options=bold>{$avgLatency}</>",
            '<options=bold>'.number_format($totalPromptTokens).'</>',
            '<options=bold>'.number_format($totalCompletionTokens).'</>',
        ];

        $table->addRow($summaryRow);
        $table->render();
    }

    private function formatLatency(float $seconds): string
    {
        if ($seconds < 1) {
            return round($seconds * 1000).'ms';
        }

        return number_format($seconds, 1).'s';
    }

    private function shortenProviderLabel(string $label): string
    {
        $parts = explode('/', $label, 2);

        if (count($parts) === 2) {
            $provider = $parts[0];
            $model = $parts[1];

            if (mb_strlen($model) > 25) {
                $model = mb_substr($model, 0, 22).'...';
            }

            return "{$provider}/{$model}";
        }

        return $label;
    }
}
