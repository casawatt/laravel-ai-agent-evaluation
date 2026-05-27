<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Casawatt\LaravelAiAgentEvaluation\Exceptions\AssertionFailureException;
use Illuminate\Support\Arr;
use Laravel\Ai\Responses\StructuredAgentResponse;

class AssertableStructuredResponse extends AbstractAssertableResponse
{
    public readonly StructuredAgentResponse $structuredResponse;

    public function __construct(StructuredAgentResponse $response, float $latencySeconds, ?string $prompt = null)
    {
        parent::__construct($response, $latencySeconds, $prompt);

        $this->structuredResponse = $response;
    }

    // --- Path-based text/value assertions ---

    public function assertContains(string $path, string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertContains', function () use ($path, $needle, $message) {
            $value = $this->stringAt($path);
            if (! str_contains($value, $needle)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] contains [{$needle}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertNotContains(string $path, string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotContains', function () use ($path, $needle, $message) {
            $value = $this->stringAt($path);
            if (str_contains($value, $needle)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] does not contain [{$needle}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertContainsIgnoringCase(string $path, string $needle, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertContainsIgnoringCase', function () use ($path, $needle, $message) {
            $value = $this->stringAt($path);
            if (mb_stripos($value, $needle) === false) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] contains [{$needle}] (case-insensitive).",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertRegex(string $path, string $pattern, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertRegex', function () use ($path, $pattern, $message) {
            $value = $this->stringAt($path);
            if (preg_match($pattern, $value) !== 1) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] matches pattern [{$pattern}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertNotRegex(string $path, string $pattern, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotRegex', function () use ($path, $pattern, $message) {
            $value = $this->stringAt($path);
            if (preg_match($pattern, $value) === 1) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] does not match pattern [{$pattern}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertStartsWith(string $path, string $prefix, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertStartsWith', function () use ($path, $prefix, $message) {
            $value = $this->stringAt($path);
            if (! str_starts_with($value, $prefix)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] starts with [{$prefix}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertEndsWith(string $path, string $suffix, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertEndsWith', function () use ($path, $suffix, $message) {
            $value = $this->stringAt($path);
            if (! str_ends_with($value, $suffix)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] ends with [{$suffix}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertEquals(string $path, mixed $expected, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertEquals', function () use ($path, $expected, $message) {
            $actual = Arr::get($this->structuredResponse->structured, $path);
            if ($actual !== $expected) {
                throw new AssertionFailureException(
                    $message ?: "Expected value at path [{$path}] to be ".var_export($expected, true).', got '.var_export($actual, true).'.',
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertEmpty(string $path, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertEmpty', function () use ($path, $message) {
            $value = Arr::get($this->structuredResponse->structured, $path);
            if (! empty($value)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] is empty.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertNotEmpty(string $path, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertNotEmpty', function () use ($path, $message) {
            $value = Arr::get($this->structuredResponse->structured, $path);
            if (empty($value)) {
                throw new AssertionFailureException(
                    $message ?: "Failed asserting that value at path [{$path}] is not empty.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertMinLength(string $path, int $min, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertMinLength', function () use ($path, $min, $message) {
            $value = $this->stringAt($path);
            $length = mb_strlen($value);
            if ($length < $min) {
                throw new AssertionFailureException(
                    $message ?: "Expected value at path [{$path}] to be at least {$min} characters, got {$length}.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertMaxLength(string $path, int $max, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertMaxLength', function () use ($path, $max, $message) {
            $value = $this->stringAt($path);
            $length = mb_strlen($value);
            if ($length > $max) {
                throw new AssertionFailureException(
                    $message ?: "Expected value at path [{$path}] to be at most {$max} characters, got {$length}.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    // --- Structure-specific assertions ---

    public function assertStructure(array $structure, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion(
            'assertStructure',
            fn () => $this->assertArrayStructure($structure, $this->structuredResponse->structured),
            $weight,
            $metric,
        );

        return $this;
    }

    public function assertHasKey(string $key, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertHasKey', function () use ($key, $message) {
            if (! Arr::has($this->structuredResponse->structured, $key)) {
                throw new AssertionFailureException(
                    $message ?: "Expected structured response to have key [{$key}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertMissingKey(string $key, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertMissingKey', function () use ($key, $message) {
            if (Arr::has($this->structuredResponse->structured, $key)) {
                throw new AssertionFailureException(
                    $message ?: "Expected structured response to not have key [{$key}].",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertCount(int $count, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertCount', function () use ($count, $message) {
            $actual = count($this->structuredResponse->structured);
            if ($actual !== $count) {
                throw new AssertionFailureException(
                    $message ?: "Expected structured response to have {$count} entries, got {$actual}.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    public function assertWhere(string $path, callable $callback, string $message = '', float $weight = 1.0, ?string $metric = null): self
    {
        $this->runAssertion('assertWhere', function () use ($path, $callback, $message) {
            $actual = Arr::get($this->structuredResponse->structured, $path);

            if (! $callback($actual)) {
                throw new AssertionFailureException(
                    $message ?: "The value at path [{$path}] did not satisfy the callback.",
                );
            }
        }, $weight, $metric);

        return $this;
    }

    // --- Internals ---

    private function stringAt(string $path): string
    {
        $value = Arr::get($this->structuredResponse->structured, $path);

        if (! is_string($value)) {
            throw new AssertionFailureException("Value at path [{$path}] is not a string.");
        }

        return $value;
    }

    private function assertArrayStructure(array $structure, mixed $data): void
    {
        if (! is_array($data)) {
            throw new AssertionFailureException('Expected data to be an array.');
        }

        foreach ($structure as $key => $value) {
            if (is_array($value) && $key === '*') {
                foreach ($data as $item) {
                    $this->assertArrayStructure($value, $item);
                }
            } elseif (is_array($value)) {
                if (! array_key_exists($key, $data)) {
                    throw new AssertionFailureException("Expected array to have key [{$key}].");
                }
                $this->assertArrayStructure($value, $data[$key]);
            } else {
                if (! array_key_exists($value, $data)) {
                    throw new AssertionFailureException("Expected array to have key [{$value}].");
                }
            }
        }
    }
}
