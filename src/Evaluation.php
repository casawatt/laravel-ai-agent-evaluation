<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;

abstract class Evaluation
{
    /** @var class-string The agent class to evaluate. */
    protected string $agent;

    /** @var Collection<int, Variant> */
    private Collection $variants;

    private ?Variant $currentVariant = null;

    /** @var Collection<int, AssertableResponse> */
    private Collection $responses;

    public function __construct()
    {
        $this->variants = new Collection;
        $this->responses = new Collection;
    }

    /**
     * Override this method to configure variants.
     */
    public function setUp(): void {}

    /**
     * Register a variant to evaluate against.
     */
    public function variant(Lab|string $provider, string $model, ?string $label = null): Variant
    {
        $variant = new Variant($provider, $model, $label);
        $this->variants->push($variant);

        return $variant;
    }

    /**
     * Get the current variant being evaluated.
     */
    public function currentVariant(): Variant
    {
        if ($this->currentVariant === null) {
            throw new \LogicException('No variant is currently set.');
        }

        return $this->currentVariant;
    }

    /**
     * Skip the current case unconditionally.
     *
     * @throws SkippedException
     */
    public function skip(string $reason = 'Skipped'): never
    {
        throw new SkippedException($reason);
    }

    /**
     * Skip the current case when the condition is true.
     * Accepts a boolean or a callable that receives the current Variant.
     *
     * @param  bool|callable(Variant): bool  $condition
     *
     * @throws SkippedException
     */
    public function skipWhen(bool|callable $condition, string $reason = 'Skipped'): void
    {
        if (is_callable($condition)) {
            $condition = $condition($this->currentVariant());
        }

        if ($condition) {
            throw new SkippedException($reason);
        }
    }

    public function agent(string $prompt, array $attachments = []): AssertableResponse|AssertableStructuredResponse
    {
        $agentClass = $this->resolveAgentClass();
        $agent = app($agentClass);

        if ($this->currentVariant?->instruction !== null) {
            $agent = new AgentDecorator($agent, $this->currentVariant->instruction);
        }

        $startTime = microtime(true);

        $response = $agent->prompt(
            prompt: $prompt,
            attachments: $attachments,
            provider: $this->currentVariant?->provider,
            model: $this->currentVariant?->model,
            timeout: config('ai-agent-evaluation.timeout'),
        );

        $latency = microtime(true) - $startTime;

        $assertable = $response instanceof StructuredAgentResponse
            ? new AssertableStructuredResponse($response, $latency)
            : new AssertableResponse($response, $latency);
        $this->responses->push($assertable);

        return $assertable;
    }

    /** @internal */
    public function setCurrentVariant(Variant $variant): void
    {
        $this->currentVariant = $variant;
    }

    /** @internal */
    public function resetResponses(): void
    {
        $this->responses = new Collection;
    }

    /** @internal */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    /** @internal */
    public function getAgentClass(): string
    {
        return $this->resolveAgentClass();
    }

    /**
     * @internal
     *
     * @return Collection<int, Variant>
     */
    public function getVariants(): Collection
    {
        if ($this->variants->isEmpty()) {
            throw new \LogicException(
                'Evaluation must define at least one variant via the setUp() method.',
            );
        }

        return $this->variants;
    }

    private function resolveAgentClass(): string
    {
        if (! isset($this->agent)) {
            throw new \LogicException(
                'Evaluation must define the $agent property with the agent class to evaluate.',
            );
        }

        return $this->agent;
    }
}
