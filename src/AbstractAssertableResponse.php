<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Casawatt\LaravelAiAgentEvaluation\Exceptions\AssertionFailureException;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AgentResponse;

abstract class AbstractAssertableResponse
{
    /** @var Collection<int, AssertionResult> */
    protected Collection $assertionResults;

    public function __construct(
        public readonly AgentResponse $response,
        public readonly float $latencySeconds,
    ) {
        $this->assertionResults = new Collection;
    }

    // --- Tool Call Assertions ---

    public function assertToolCalled(string $toolName, string $message = '', float $weight = 1.0, ?string $metric = null): static
    {
        $this->runAssertion('assertToolCalled', function () use ($toolName, $message) {
            $called = $this->response->toolCalls->contains(fn ($tc) => $tc->name === $toolName);
            if (! $called) {
                throw new AssertionFailureException(
                    $message ?: "Expected tool [{$toolName}] to have been called.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertToolNotCalled(string $toolName, string $message = '', float $weight = 1.0, ?string $metric = null): static
    {
        $this->runAssertion('assertToolNotCalled', function () use ($toolName, $message) {
            $called = $this->response->toolCalls->contains(fn ($tc) => $tc->name === $toolName);
            if ($called) {
                throw new AssertionFailureException(
                    $message ?: "Expected tool [{$toolName}] to not have been called.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertToolCalledTimes(string $toolName, int $times, string $message = '', float $weight = 1.0, ?string $metric = null): static
    {
        $actual = $this->response->toolCalls->filter(fn ($tc) => $tc->name === $toolName)->count();

        $this->runAssertion('assertToolCalledTimes', function () use ($toolName, $times, $actual, $message) {
            if ($actual !== $times) {
                throw new AssertionFailureException(
                    $message ?: "Expected tool [{$toolName}] to have been called {$times} time(s), got {$actual}.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    // --- Performance Assertions ---

    public function assertLatencyBelow(float $maxSeconds, string $message = '', float $weight = 1.0, ?string $metric = null): static
    {
        $this->runAssertion('assertLatencyBelow', function () use ($maxSeconds, $message) {
            if ($this->latencySeconds >= $maxSeconds) {
                throw new AssertionFailureException(
                    $message ?: "Expected latency below {$maxSeconds}s, got {$this->latencySeconds}s.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertTokensBelow(int $maxTokens, string $message = '', float $weight = 1.0, ?string $metric = null): static
    {
        $total = $this->response->usage->promptTokens + $this->response->usage->completionTokens;

        $this->runAssertion('assertTokensBelow', function () use ($maxTokens, $total, $message) {
            if ($total >= $maxTokens) {
                throw new AssertionFailureException(
                    $message ?: "Expected total tokens below {$maxTokens}, got {$total}.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    // --- Custom Assertion ---

    public function assert(callable $callback, string $message = '', float $weight = 1.0, ?string $metric = null): static
    {
        $this->runAssertion('assert', function () use ($callback, $message) {
            if (! $callback($this->response)) {
                throw new AssertionFailureException(
                    $message ?: 'Custom assertion failed.',
                );
            }
        }, $weight, $metric);

        return $this;
    }

    // --- Assertion Results ---

    /** @return Collection<int, AssertionResult> */
    public function getAssertionResults(): Collection
    {
        return $this->assertionResults;
    }

    // --- Internals ---

    protected function runAssertion(string $name, callable $assertion, float $weight, ?string $metric = null): void
    {
        try {
            $assertion();
            $this->assertionResults->push(new AssertionResult($name, true, $weight, metric: $metric));
        } catch (AssertionFailureException $e) {
            $this->assertionResults->push(new AssertionResult($name, false, $weight, $e->getMessage(), $metric));
        }
    }
}
