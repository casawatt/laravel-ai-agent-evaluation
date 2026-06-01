<?php

namespace Casawatt\LaravelAiAgentEvaluation\Commands;

use Casawatt\LaravelAiAgentEvaluation\EvaluationResult;
use Casawatt\LaravelAiAgentEvaluation\EvaluationRunner;
use Casawatt\LaravelAiAgentEvaluation\Reporter\ConsoleReporter;
use Casawatt\LaravelAiAgentEvaluation\ResultStatus;
use Casawatt\LaravelAiAgentEvaluation\Storage\StorageInterface;
use Casawatt\LaravelAiAgentEvaluation\SuiteBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Fork\Fork;

class RunAgentEvaluationCommand extends Command
{
    protected $signature = 'agent-evaluation
        {--filter= : Filter evaluations by name}
        {--variant= : Filter to a specific variant by label}
        {--resume : Resume the latest evaluation, skipping already-run cases}
        {--retry-errors : Retry only error results from the latest evaluation}
        {--retry-failed : Retry only failed results from the latest evaluation}
        {--parallel=0 : Number of cases to run in parallel (requires pcntl)}';

    protected $description = 'Run AI agent evaluations';

    public function handle(StorageInterface $storage): int
    {
        $path = config('ai-agent-evaluation.path', base_path('agent-evaluations'));
        $concurrency = (int) $this->option('parallel') ?: (int) config('ai-agent-evaluation.parallel', 1);

        $runner = new EvaluationRunner($path);

        [$previousResults, $runId] = $this->loadPreviousResults($storage);
        $runId ??= $storage->createRun();

        $this->components->info('Running agent evaluations...');
        $this->output->writeln('');

        if ($concurrency > 1) {
            return $this->handleParallel($runner, $storage, $runId, $previousResults, $concurrency);
        }

        return $this->handleSequential($runner, $storage, $runId, $previousResults);
    }

    private function handleSequential(
        EvaluationRunner $runner,
        StorageInterface $storage,
        string $runId,
        ?Collection $previousResults,
    ): int {
        $passed = 0;
        $failed = 0;
        $skipped = 0;
        $reused = 0;

        $suites = $runner->run(
            filter: $this->option('filter'),
            variantFilter: $this->option('variant'),
            onCaseComplete: function (EvaluationResult $result) use (&$passed, &$failed, &$skipped, &$reused) {
                $this->reportProgress($result, $passed, $failed, $skipped, $reused);
            },
            previousResults: $previousResults,
            storage: $storage,
            runId: $runId,
        );

        return $this->renderResults($storage, $runId, $passed, $failed, $skipped, $reused);
    }

    private function handleParallel(
        EvaluationRunner $runner,
        StorageInterface $storage,
        string $runId,
        ?Collection $previousResults,
        int $concurrency,
    ): int {
        [$workUnits, $reusedResults] = $runner->discoverWorkUnits(
            filter: $this->option('filter'),
            variantFilter: $this->option('variant'),
            previousResults: $previousResults,
        );

        if ($workUnits->isEmpty() && $reusedResults->isEmpty()) {
            $this->components->warn('No evaluations found.');

            return self::SUCCESS;
        }

        $passed = 0;
        $failed = 0;
        $skipped = 0;
        $reused = 0;

        // Report and persist reused results
        foreach ($reusedResults as $result) {
            $storage->saveResult($runId, $result->resultKey(), $result->toStorageArray());
            $this->reportProgress($result, $passed, $failed, $skipped, $reused);
        }

        if ($workUnits->isNotEmpty()) {
            $closures = $workUnits->map(
                fn ($unit) => fn () => $runner->executeSingleWorkUnit($unit),
            )->all();

            $results = Fork::new()
                ->concurrent($concurrency)
                ->run(...$closures);

            foreach ($results as $result) {
                $storage->saveResult($runId, $result->resultKey(), $result->toStorageArray());
                $this->reportProgress($result, $passed, $failed, $skipped, $reused);
            }
        }

        return $this->renderResults($storage, $runId, $passed, $failed, $skipped, $reused);
    }

    private function reportProgress(EvaluationResult $result, int &$passed, int &$failed, int &$skipped, int &$reused): void
    {
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
    }

    private function renderResults(
        StorageInterface $storage,
        string $runId,
        int $passed,
        int $failed,
        int $skipped,
        int $reused,
    ): int {
        $this->output->writeln('');
        $this->output->writeln('');

        $allResults = $storage->getResults($runId);
        $suites = SuiteBuilder::fromStorageResults($allResults);

        if ($suites->isEmpty()) {
            $this->components->warn('No evaluations found.');

            return self::SUCCESS;
        }

        $consoleReporter = new ConsoleReporter($this->output);
        $consoleReporter->render($suites);

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
        if (! $this->option('resume') && ! $this->option('retry-errors') && ! $this->option('retry-failed')) {
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

        if ($this->option('retry-failed')) {
            $this->components->info("Retrying failures from run {$latestRunId}...");

            return [new Collection($storage->getResultsExcludingStatus($latestRunId, ResultStatus::Failed)), $latestRunId];
        }

        $this->components->info("Resuming run {$latestRunId}...");

        return [new Collection($storage->getResults($latestRunId)), $latestRunId];
    }

    private function renderFailures(Collection $suites): void
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
