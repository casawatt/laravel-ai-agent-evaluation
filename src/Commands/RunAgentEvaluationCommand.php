<?php

namespace Casawatt\LaravelAiAgentEvaluation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Casawatt\LaravelAiAgentEvaluation\EvaluationResult;
use Casawatt\LaravelAiAgentEvaluation\EvaluationRunner;
use Casawatt\LaravelAiAgentEvaluation\Reporter\ConsoleReporter;
use Casawatt\LaravelAiAgentEvaluation\ResultStatus;
use Casawatt\LaravelAiAgentEvaluation\Storage\StorageInterface;
use Casawatt\LaravelAiAgentEvaluation\SuiteBuilder;

class RunAgentEvaluationCommand extends Command
{
    protected $signature = 'agent-evaluation
        {--filter= : Filter evaluations by name}
        {--variant= : Filter to a specific variant by label}
        {--resume : Resume the latest evaluation, skipping already-run cases}
        {--retry-errors : Retry only error results from the latest evaluation}';

    protected $description = 'Run AI agent evaluations';

    public function handle(StorageInterface $storage): int
    {
        $path = config('ai-agent-evaluation.path', base_path('agent-evaluations'));

        $runner = new EvaluationRunner($path);

        [$previousResults, $runId] = $this->loadPreviousResults($storage);
        $runId ??= $storage->createRun();

        $this->components->info('Running agent evaluations...');
        $this->output->writeln('');

        $passed = 0;
        $failed = 0;
        $skipped = 0;
        $reused = 0;

        $suites = $runner->run(
            filter: $this->option('filter'),
            variantFilter: $this->option('variant'),
            onCaseComplete: function (EvaluationResult $result) use (&$passed, &$failed, &$skipped, &$reused) {
                if ($result->reused) {
                    $this->output->write('<fg=gray>-</>');
                    $reused++;
                } elseif ($result->skipped()) {
                    $this->output->write('<fg=yellow>S</>');
                    $skipped++;
                } elseif ($result->passed()) {
                    $this->output->write('<fg=green>.</>');
                    $passed++;
                } elseif ($result->errored()) {
                    $this->output->write('<fg=red>E</>');
                    $failed++;
                } else {
                    $this->output->write('<fg=red>F</>');
                    $failed++;
                }
            },
            previousResults: $previousResults,
            storage: $storage,
            runId: $runId,
        );

        $this->output->writeln('');
        $this->output->writeln('');

        if ($suites->isEmpty()) {
            $this->components->warn('No evaluations found.');

            return self::SUCCESS;
        }

        $allResults = $storage->getResults($runId);
        $completeSuites = SuiteBuilder::fromStorageResults($allResults);

        $consoleReporter = new ConsoleReporter($this->output);
        $consoleReporter->render($completeSuites);

        $this->components->info("Results saved to run {$runId}.");

        $this->renderFailures($suites);

        $allPassed = $suites->every(fn ($s) => $s->allPassed());

        $extra = collect()
            ->when($reused > 0, fn ($c) => $c->push("{$reused} cached"))
            ->when($skipped > 0, fn ($c) => $c->push("{$skipped} skipped"))
            ->implode(', ');
        $extraInfo = $extra !== '' ? " ({$extra})" : '';

        if ($allPassed) {
            $this->components->info("{$passed} evaluation(s) passed{$extraInfo}.");
        } else {
            $this->components->error("{$failed} failed, {$passed} passed{$extraInfo}.");
        }

        return $allPassed ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{Collection|null, string|null}
     */
    private function loadPreviousResults(StorageInterface $storage): array
    {
        if (! $this->option('resume') && ! $this->option('retry-errors')) {
            return [null, null];
        }

        $latestRunId = $storage->getLatestRunId();

        if ($latestRunId === null) {
            $this->components->warn('No previous evaluation found.');

            return [null, null];
        }

        if ($this->option('retry-errors')) {
            $this->components->info("Retrying errors from run {$latestRunId}...");

            return [new Collection($storage->getResultsExcludingStatus($latestRunId, ResultStatus::Error)), $latestRunId];
        }

        $this->components->info("Resuming run {$latestRunId}...");

        return [new Collection($storage->getResults($latestRunId)), $latestRunId];
    }

    private function renderFailures($suites): void
    {
        $failures = $suites->flatMap(fn ($s) => $s->results->filter(
            fn (EvaluationResult $r) => $r->failed() || $r->errored(),
        ));

        if ($failures->isEmpty()) {
            return;
        }

        $this->output->writeln('<fg=red>Failures:</>');
        $this->output->writeln('');

        foreach ($failures as $result) {
            $statusLabel = $result->errored() ? 'ERROR' : 'FAIL';

            $this->output->writeln(sprintf(
                '  <fg=red>%s</> %s::%s [%s]',
                $statusLabel,
                $result->evaluationName,
                $result->caseName,
                $result->variantLabel(),
            ));

            if ($result->failureMessage) {
                $this->output->writeln("        {$result->failureMessage}");
            }

            $this->output->writeln('');
        }
    }
}
