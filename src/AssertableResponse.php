<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Casawatt\LaravelAiAgentEvaluation\Exceptions\AssertionFailureException;

class AssertableResponse extends AbstractAssertableResponse
{
    // --- Text Assertions ---

    public function assertContains(string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertContains', function () use ($needle, $message) {
            if (! str_contains($this->response->text, $needle)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that response text contains [{$needle}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertNotContains(string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotContains', function () use ($needle, $message) {
            if (str_contains($this->response->text, $needle)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that response text does not contain [{$needle}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertContainsIgnoringCase(string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertContainsIgnoringCase', function () use ($needle, $message) {
            if (mb_stripos($this->response->text, $needle) === false) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that response text contains [{$needle}] (case-insensitive).",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertRegex(string $pattern, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertRegex', function () use ($pattern, $message) {
            if (preg_match($pattern, $this->response->text) !== 1) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that response text matches pattern [{$pattern}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertNotRegex(string $pattern, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotRegex', function () use ($pattern, $message) {
            if (preg_match($pattern, $this->response->text) === 1) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that response text does not match pattern [{$pattern}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertStartsWith(string $prefix, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertStartsWith', function () use ($prefix, $message) {
            if (! str_starts_with($this->response->text, $prefix)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that response text starts with [{$prefix}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertEndsWith(string $suffix, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertEndsWith', function () use ($suffix, $message) {
            if (! str_ends_with($this->response->text, $suffix)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that response text ends with [{$suffix}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertEquals(string $expected, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertEquals', function () use ($expected, $message) {
            if ($this->response->text !== $expected) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that response text equals [{$expected}]. Got [{$this->response->text}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertEmpty(string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertEmpty', function () use ($message) {
            if ($this->response->text !== '') {
                throw new AssertionFailureException(
                    $message ?: 'Failed asserting that response text is empty.',
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertNotEmpty(string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotEmpty', function () use ($message) {
            if ($this->response->text === '') {
                throw new AssertionFailureException(
                    $message ?: 'Failed asserting that response text is not empty.',
                );
            }
        }, $weight, $metric);

        return $this;
    }

    // --- Length Assertions ---

    public function assertMinLength(int $min, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertMinLength', function () use ($min, $message) {
            $length = mb_strlen($this->response->text);
            if ($length < $min) {
                throw new AssertionFailureException(
                    $message ?: "Expected response text to be at least {$min} characters, got {$length}.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertMaxLength(int $max, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertMaxLength', function () use ($max, $message) {
            $length = mb_strlen($this->response->text);
            if ($length > $max) {
                throw new AssertionFailureException(
                    $message ?: "Expected response text to be at most {$max} characters, got {$length}.",
                );
            }
        }, $weight, $metric);

        return $this;
    }
}
