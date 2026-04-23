<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AgentResponse;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

class AssertableResponse
{
    /** @var Collection<int, AssertionResult> */
    private Collection $assertionResults;

    public function __construct(
        public readonly AgentResponse $response,
        public readonly float $latencySeconds,
    ) {
        $this->assertionResults = new Collection;
    }

    // --- Text Assertions ---

    public function assertContains(string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertContains', fn () => Assert::assertStringContainsString($needle, $this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertNotContains(string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotContains', fn () => Assert::assertStringNotContainsString($needle, $this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertContainsIgnoringCase(string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertContainsIgnoringCase', fn () => Assert::assertStringContainsStringIgnoringCase($needle, $this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertRegex(string $pattern, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertRegex', fn () => Assert::assertMatchesRegularExpression($pattern, $this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertNotRegex(string $pattern, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotRegex', fn () => Assert::assertDoesNotMatchRegularExpression($pattern, $this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertStartsWith(string $prefix, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertStartsWith', fn () => Assert::assertStringStartsWith($prefix, $this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertEndsWith(string $suffix, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertEndsWith', fn () => Assert::assertStringEndsWith($suffix, $this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertExactly(string $expected, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertExactly', fn () => Assert::assertSame($expected, $this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertEmpty(string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertEmpty', fn () => Assert::assertEmpty($this->response->text, $message), $weight, $metric);

        return $this;
    }

    public function assertNotEmpty(string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotEmpty', fn () => Assert::assertNotEmpty($this->response->text, $message), $weight, $metric);

        return $this;
    }

    // --- Length Assertions ---

    public function assertMinLength(int $min, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertMinLength', fn () => Assert::assertGreaterThanOrEqual(
            $min,
            mb_strlen($this->response->text),
            $message ?: "Expected response text to be at least {$min} characters.",
        ), $weight, $metric);

        return $this;
    }

    public function assertMaxLength(int $max, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertMaxLength', fn () => Assert::assertLessThanOrEqual(
            $max,
            mb_strlen($this->response->text),
            $message ?: "Expected response text to be at most {$max} characters.",
        ), $weight, $metric);

        return $this;
    }

    // --- Tool Call Assertions ---

    public function assertToolCalled(string $toolName, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertToolCalled', fn () => Assert::assertTrue(
            $this->response->toolCalls->contains(fn ($tc) => $tc->name === $toolName),
            $message ?: "Expected tool [{$toolName}] to have been called.",
        ), $weight, $metric);

        return $this;
    }

    public function assertToolNotCalled(string $toolName, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertToolNotCalled', fn () => Assert::assertFalse(
            $this->response->toolCalls->contains(fn ($tc) => $tc->name === $toolName),
            $message ?: "Expected tool [{$toolName}] to not have been called.",
        ), $weight, $metric);

        return $this;
    }

    public function assertToolCalledTimes(string $toolName, int $times, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $actual = $this->response->toolCalls->filter(fn ($tc) => $tc->name === $toolName)->count();

        $this->runAssertion('assertToolCalledTimes', fn () => Assert::assertSame(
            $times,
            $actual,
            $message ?: "Expected tool [{$toolName}] to have been called {$times} time(s), got {$actual}.",
        ), $weight, $metric);

        return $this;
    }

    // --- Performance Assertions ---

    public function assertLatencyBelow(float $maxSeconds, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertLatencyBelow', fn () => Assert::assertLessThan(
            $maxSeconds,
            $this->latencySeconds,
            $message ?: "Expected latency below {$maxSeconds}s, got {$this->latencySeconds}s.",
        ), $weight, $metric);

        return $this;
    }

    public function assertTokensBelow(int $maxTokens, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $total = $this->response->usage->promptTokens + $this->response->usage->completionTokens;

        $this->runAssertion('assertTokensBelow', fn () => Assert::assertLessThan(
            $maxTokens,
            $total,
            $message ?: "Expected total tokens below {$maxTokens}, got {$total}.",
        ), $weight, $metric);

        return $this;
    }

    // --- Custom Assertion ---

    public function assert(callable $callback, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assert', fn () => Assert::assertTrue(
            $callback($this->response),
            $message ?: 'Custom assertion failed.',
        ), $weight, $metric);

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
        } catch (AssertionFailedError $e) {
            $this->assertionResults->push(new AssertionResult($name, false, $weight, $e->getMessage(), $metric));
        }
    }
}
