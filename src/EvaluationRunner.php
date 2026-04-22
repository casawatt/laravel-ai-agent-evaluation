<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Casawatt\LaravelAiAgentEvaluation\Attributes\EvaluationCase;
use Casawatt\LaravelAiAgentEvaluation\Attributes\With;
use Casawatt\LaravelAiAgentEvaluation\Storage\StorageInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\Usage;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

class EvaluationRunner
{
    public function __construct(
        private readonly string $evaluationsPath,
    ) {}

    /**
     * @return array<string, Evaluation> Keyed by evaluation name.
     */
    public function discover(?string $filter = null): array
    {
        if (! is_dir($this->evaluationsPath)) {
            return [];
        }

        $evaluations = [];

        $files = Finder::create()
            ->files()
            ->name('*Evaluation.php')
            ->notName('.*')
            ->in($this->evaluationsPath)
            ->depth(0);

        foreach ($files as $file) {
            $name = $file->getBasename('.php');

            if ($filter !== null && ! Str::contains($name, $filter, ignoreCase: true)) {
                continue;
            }

            $evaluation = require $file->getRealPath();

            if (! $evaluation instanceof Evaluation) {
                continue;
            }

            $evaluation->setUp();

            $evaluations[$name] = $evaluation;
        }

        return $evaluations;
    }

    /**
     * @param  Collection<string, array>|null  $previousResults  Keyed by resultKey, values are previous JSON result data.
     * @return Collection<int, EvaluationSuite>
     */
    public function run(
        ?string $filter = null,
        ?string $variantFilter = null,
        ?callable $onCaseComplete = null,
        ?Collection $previousResults = null,
        ?StorageInterface $storage = null,
        ?string $runId = null,
    ): Collection {
        $suites = new Collection;

        foreach ($this->discover($filter) as $name => $evaluation) {
            $agentClass = $evaluation->getAgentClass();
            $variants = $evaluation->getVariants();
            $cases = $this->extractCases($evaluation);

            $suite = new EvaluationSuite($name, $agentClass);

            foreach ($variants as $variant) {
                if ($variantFilter !== null && ! Str::contains($variant->label, $variantFilter, ignoreCase: true)) {
                    continue;
                }

                $evaluation->setCurrentVariant($variant);

                foreach ($cases as $case) {
                    foreach ($this->expandCase($evaluation, $case) as [$args, $dataSetLabel]) {
                        $result = $this->executeCaseOrReuse($evaluation, $case, $variant, $name, $previousResults, $args, $dataSetLabel);
                        $suite->add($result);
                        $this->persistResult($storage, $runId, $result);

                        if ($onCaseComplete) {
                            $onCaseComplete($result);
                        }
                    }
                }
            }

            $suites->push($suite);
        }

        return $suites;
    }

    /**
     * @return array<ReflectionMethod>
     */
    private function extractCases(Evaluation $evaluation): array
    {
        $reflection = new ReflectionClass($evaluation);
        $cases = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(EvaluationCase::class);

            if ($attributes !== []) {
                $cases[] = $method;
            }
        }

        return $cases;
    }

    /**
     * Yield [args, dataSetLabel] tuples for a case method.
     *
     * @return iterable<array{array, string|null}>
     */
    private function expandCase(Evaluation $evaluation, ReflectionMethod $case): iterable
    {
        $dataSets = $this->resolveDataSets($evaluation, $case);

        if ($dataSets === null) {
            yield [[], null];
        } else {
            foreach ($dataSets as $label => $args) {
                yield [(array) $args, (string) $label];
            }
        }
    }

    /**
     * @return array<string, array>|null Null if no #[With] attribute.
     */
    private function resolveDataSets(Evaluation $evaluation, ReflectionMethod $method): ?array
    {
        $attributes = $method->getAttributes(With::class);

        if ($attributes === []) {
            return null;
        }

        $with = $attributes[0]->newInstance();

        return $evaluation->{$with->method}();
    }

    private function getCaseDescription(ReflectionMethod $method, ?string $dataSetLabel = null): string
    {
        $attributes = $method->getAttributes(EvaluationCase::class);

        if ($attributes !== []) {
            $description = $attributes[0]->newInstance()->description;

            if ($description !== null) {
                return $dataSetLabel !== null ? "{$description} ({$dataSetLabel})" : $description;
            }
        }

        $base = Str::of($method->getName())
            ->replace('_', ' ')
            ->trim()
            ->toString();

        return $dataSetLabel !== null ? "{$base} ({$dataSetLabel})" : $base;
    }

    private function getCaseName(ReflectionMethod $method, ?string $dataSetLabel = null): string
    {
        $name = $method->getName();

        return $dataSetLabel !== null ? "{$name} ({$dataSetLabel})" : $name;
    }

    private function executeCaseOrReuse(
        Evaluation $evaluation,
        ReflectionMethod $case,
        Variant $variant,
        string $evaluationName,
        ?Collection $previousResults,
        array $args = [],
        ?string $dataSetLabel = null,
    ): EvaluationResult {
        $caseName = $this->getCaseName($case, $dataSetLabel);
        $resultKey = $evaluationName.'::'.$caseName.'::'.$variant->label;

        if ($previousResults !== null && $previousResults->has($resultKey)) {
            $previous = $previousResults->get($resultKey);
            $result = SuiteBuilder::buildResult($previous, $evaluationName);

            return new EvaluationResult(
                evaluationName: $result->evaluationName,
                caseName: $result->caseName,
                caseDescription: $result->caseDescription,
                variant: $variant,
                status: $result->status,
                failureMessage: $result->failureMessage,
                skipReason: $result->skipReason,
                latencySeconds: $result->latencySeconds,
                usage: $result->usage,
                responseText: $result->responseText,
                assertionResults: $result->assertionResults,
                reused: true,
            );
        }

        return $this->executeCase($evaluation, $case, $variant, $evaluationName, $args, $dataSetLabel);
    }

    private function executeCase(
        Evaluation $evaluation,
        ReflectionMethod $case,
        Variant $variant,
        string $evaluationName,
        array $args = [],
        ?string $dataSetLabel = null,
    ): EvaluationResult {
        $evaluation->resetResponses();

        $caseName = $this->getCaseName($case, $dataSetLabel);
        $caseDescription = $this->getCaseDescription($case, $dataSetLabel);

        try {
            $case->invoke($evaluation, ...$args);

            $responses = $evaluation->getResponses();
            $latency = $responses->sum('latencySeconds');
            $usage = $this->aggregateUsage($responses);
            $lastText = $responses->last()?->response->text;
            $assertionResults = $this->collectAssertionResults($responses);
            $hasFailed = $assertionResults->contains(fn (AssertionResult $r) => ! $r->passed);

            return new EvaluationResult(
                evaluationName: $evaluationName,
                caseName: $caseName,
                caseDescription: $caseDescription,
                variant: $variant,
                status: $hasFailed ? ResultStatus::Failed : ResultStatus::Passed,
                failureMessage: $hasFailed
                    ? $assertionResults->where('passed', false)->first()?->message
                    : null,
                latencySeconds: $latency,
                usage: $usage,
                responseText: $lastText,
                assertionResults: $assertionResults->isNotEmpty() ? $assertionResults : null,
            );
        } catch (SkippedException $e) {
            return new EvaluationResult(
                evaluationName: $evaluationName,
                caseName: $caseName,
                caseDescription: $caseDescription,
                variant: $variant,
                status: ResultStatus::Skipped,
                skipReason: $e->getMessage(),
            );
        } catch (AssertionFailedError $e) {
            $responses = $evaluation->getResponses();
            $latency = $responses->sum('latencySeconds');
            $usage = $this->aggregateUsage($responses);

            return new EvaluationResult(
                evaluationName: $evaluationName,
                caseName: $caseName,
                caseDescription: $caseDescription,
                variant: $variant,
                status: ResultStatus::Failed,
                failureMessage: $e->getMessage(),
                latencySeconds: $latency ?: null,
                usage: $usage,
                exception: $e,
            );
        } catch (\Throwable $e) {
            $responses = $evaluation->getResponses();
            $latency = $responses->sum('latencySeconds');
            $usage = $this->aggregateUsage($responses);

            return new EvaluationResult(
                evaluationName: $evaluationName,
                caseName: $caseName,
                caseDescription: $caseDescription,
                variant: $variant,
                status: ResultStatus::Error,
                failureMessage: $e->getMessage(),
                latencySeconds: $latency ?: null,
                usage: $usage,
                exception: $e,
            );
        }
    }

    private function aggregateUsage(Collection $responses): Usage
    {
        return $responses->reduce(
            fn (Usage $carry, AssertableResponse $r) => $carry->add($r->response->usage),
            new Usage,
        );
    }

    /**
     * @return Collection<int, AssertionResult>
     */
    private function collectAssertionResults(Collection $responses): Collection
    {
        return $responses->flatMap(
            fn (AssertableResponse $r) => $r->getAssertionResults(),
        );
    }

    private function persistResult(?StorageInterface $storage, ?string $runId, EvaluationResult $result): void
    {
        if ($storage === null || $runId === null) {
            return;
        }

        $storage->saveResult($runId, $result->resultKey(), [
            'evaluation' => $result->evaluationName,
            'case' => $result->caseName,
            'description' => $result->caseDescription,
            'variant' => $result->variantLabel(),
            'provider' => $result->variant->providerValue(),
            'model' => $result->variant->model,
            'instruction' => $result->variant->instruction,
            'status' => $result->status->value,
            'failure_message' => $result->failureMessage,
            'skip_reason' => $result->skipReason,
            'latency_seconds' => $result->latencySeconds,
            'usage' => $result->usage?->toArray(),
            'response_text' => $result->responseText,
            'score' => $result->hasWeightedAssertions() ? [
                'passed_weight' => $result->passedWeight(),
                'total_weight' => $result->totalWeight(),
                'assertions' => $result->assertionResults?->map(fn ($a) => [
                    'assertion' => $a->assertion,
                    'passed' => $a->passed,
                    'weight' => $a->weight,
                    'message' => $a->message,
                ])->all(),
            ] : null,
        ]);
    }
}
