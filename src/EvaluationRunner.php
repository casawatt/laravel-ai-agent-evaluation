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
        return array_map(
            fn (array $entry) => $entry['evaluation'],
            $this->discoverWithPaths($filter),
        );
    }

    /**
     * @return array<string, array{evaluation: Evaluation, file: string}> Keyed by evaluation name.
     */
    private function discoverWithPaths(?string $filter = null): array
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

            // Each evaluation file must return an Evaluation instance (typically via anonymous class).
            // Named classes risk duplicate-definition fatal errors if two files share a class name.
            $evaluation = require $file->getRealPath();

            if (! $evaluation instanceof Evaluation) {
                continue;
            }

            $evaluation->setUp();

            $this->resolveCostForVariants($evaluation);

            $evaluations[$name] = [
                'evaluation' => $evaluation,
                'file' => $file->getRealPath(),
            ];
        }

        return $evaluations;
    }

    /**
     * Discover all work units without executing them.
     *
     * @param  Collection<string, array>|null  $previousResults  Keyed by resultKey.
     * @return array{Collection<int, WorkUnit>, Collection<int, EvaluationResult>}
     */
    public function discoverWorkUnits(
        ?string $filter = null,
        ?string $variantFilter = null,
        ?Collection $previousResults = null,
    ): array {
        $workUnits = new Collection;
        $reusedResults = new Collection;

        foreach ($this->discoverWithPaths($filter) as $name => $entry) {
            $evaluation = $entry['evaluation'];
            $filePath = $entry['file'];
            $agentClass = $evaluation->getAgentClass();
            $variants = $evaluation->getVariants();
            $cases = $this->extractCases($evaluation);

            foreach ($variants as $variant) {
                if ($variantFilter !== null && ! Str::contains($variant->label, $variantFilter, ignoreCase: true)) {
                    continue;
                }

                foreach ($cases as $case) {
                    foreach ($this->expandCase($evaluation, $case) as [$args, $dataSetLabel]) {
                        $caseName = $this->getCaseName($case, $dataSetLabel);
                        $caseDescription = $this->getCaseDescription($case, $dataSetLabel);
                        $resultKey = $name.'::'.$caseName.'::'.$variant->label;

                        if ($previousResults !== null && $previousResults->has($resultKey)) {
                            $previous = $previousResults->get($resultKey);
                            $result = SuiteBuilder::buildResult($previous, $name);

                            $reusedResults->push(new EvaluationResult(
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
                                agentClass: $agentClass,
                            ));

                            continue;
                        }

                        $workUnits->push(new WorkUnit(
                            evaluationFile: $filePath,
                            evaluationName: $name,
                            methodName: $case->getName(),
                            caseName: $caseName,
                            caseDescription: $caseDescription,
                            variant: $variant,
                            args: $args,
                            dataSetLabel: $dataSetLabel,
                            resultKey: $resultKey,
                            agentClass: $agentClass,
                        ));
                    }
                }
            }
        }

        return [$workUnits, $reusedResults];
    }

    /**
     * Execute a single work unit in isolation.
     * Loads a fresh Evaluation instance from the file.
     */
    public function executeSingleWorkUnit(WorkUnit $unit): EvaluationResult
    {
        $evaluation = require $unit->evaluationFile;

        if (! $evaluation instanceof Evaluation) {
            return new EvaluationResult(
                evaluationName: $unit->evaluationName,
                caseName: $unit->caseName,
                caseDescription: $unit->caseDescription,
                variant: $unit->variant,
                status: ResultStatus::Error,
                failureMessage: 'Evaluation file did not return an Evaluation instance.',
                agentClass: $unit->agentClass,
            );
        }

        $evaluation->setUp();
        $evaluation->setCurrentVariant($unit->variant);
        $evaluation->resetResponses();

        $agentClass = $unit->agentClass ?? $evaluation->getAgentClass();
        $method = (new ReflectionClass($evaluation))->getMethod($unit->methodName);

        return $this->executeCase($evaluation, $method, $unit->variant, $unit->evaluationName, $unit->args, $unit->dataSetLabel, $agentClass)
            ->withoutException();
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
                        $result = $this->executeCaseOrReuse($evaluation, $case, $variant, $name, $previousResults, $args, $dataSetLabel, $agentClass);
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
        ?string $agentClass = null,
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
                agentClass: $agentClass,
            );
        }

        return $this->executeCase($evaluation, $case, $variant, $evaluationName, $args, $dataSetLabel, $agentClass);
    }

    private function executeCase(
        Evaluation $evaluation,
        ReflectionMethod $case,
        Variant $variant,
        string $evaluationName,
        array $args = [],
        ?string $dataSetLabel = null,
        ?string $agentClass = null,
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
                agentClass: $agentClass,
            );
        } catch (SkippedException $e) {
            return new EvaluationResult(
                evaluationName: $evaluationName,
                caseName: $caseName,
                caseDescription: $caseDescription,
                variant: $variant,
                status: ResultStatus::Skipped,
                skipReason: $e->getMessage(),
                agentClass: $agentClass,
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
                agentClass: $agentClass,
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
                agentClass: $agentClass,
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

        $storage->saveResult($runId, $result->resultKey(), $result->toStorageArray());
    }

    private function resolveCostForVariants(Evaluation $evaluation): void
    {
        /** @var array<class-string<CostResolverInterface>> $resolverClasses */
        $resolverClasses = config('ai-agent-evaluation.cost_resolvers', []);

        if ($resolverClasses === []) {
            return;
        }

        $resolvers = array_map(fn (string $class) => app($class), $resolverClasses);

        foreach ($evaluation->getVariants() as $variant) {
            if ($variant->hasPricing()) {
                continue;
            }

            foreach ($resolvers as $resolver) {
                $price = $resolver->resolve($variant->provider, $variant->model);

                if ($price !== null) {
                    $variant->pricing($price->inputPerMillion, $price->outputPerMillion);
                    break;
                }
            }
        }
    }
}
