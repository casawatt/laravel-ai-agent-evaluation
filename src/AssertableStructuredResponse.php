<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Illuminate\Support\Arr;
use Laravel\Ai\Responses\StructuredAgentResponse;
use PHPUnit\Framework\Assert;

class AssertableStructuredResponse extends AssertableResponse
{
    public readonly StructuredAgentResponse $structuredResponse;

    public function __construct(StructuredAgentResponse $response, float $latencySeconds)
    {
        parent::__construct($response, $latencySeconds);

        $this->structuredResponse = $response;
    }

    public function assertStructure(array $structure, string $message = '', float $weight = 1.0): self
    {
        $this->runAssertion(
            'assertStructure',
            fn () => $this->assertArrayStructure($structure, $this->structuredResponse->structured),
            $weight,
        );

        return $this;
    }

    public function assertPath(string $path, mixed $expected, string $message = '', float $weight = 1.0): self
    {
        $this->runAssertion('assertPath', function () use ($path, $expected, $message) {
            $actual = Arr::get($this->structuredResponse->structured, $path);

            Assert::assertSame(
                $expected,
                $actual,
                $message ?: "Expected path [{$path}] to be ".var_export($expected, true).'.',
            );
        }, $weight);

        return $this;
    }

    public function assertPathContains(string $path, string $needle, string $message = '', float $weight = 1.0): self
    {
        $this->runAssertion('assertPathContains', function () use ($path, $needle, $message) {
            $actual = Arr::get($this->structuredResponse->structured, $path);

            Assert::assertIsString($actual, "Value at path [{$path}] is not a string.");
            Assert::assertStringContainsString(
                $needle,
                $actual,
                $message ?: "Expected path [{$path}] to contain [{$needle}].",
            );
        }, $weight);

        return $this;
    }

    public function assertHasKey(string $key, string $message = '', float $weight = 1.0): self
    {
        $this->runAssertion('assertHasKey', fn () => Assert::assertArrayHasKey(
            $key,
            $this->structuredResponse->structured,
            $message ?: "Expected structured response to have key [{$key}].",
        ), $weight);

        return $this;
    }

    public function assertMissingKey(string $key, string $message = '', float $weight = 1.0): self
    {
        $this->runAssertion('assertMissingKey', fn () => Assert::assertArrayNotHasKey(
            $key,
            $this->structuredResponse->structured,
            $message ?: "Expected structured response to not have key [{$key}].",
        ), $weight);

        return $this;
    }

    public function assertCount(int $count, string $message = '', float $weight = 1.0): self
    {
        $this->runAssertion('assertCount', fn () => Assert::assertCount(
            $count,
            $this->structuredResponse->structured,
            $message ?: "Expected structured response to have {$count} entries.",
        ), $weight);

        return $this;
    }

    public function assertWhere(string $path, callable $callback, string $message = '', float $weight = 1.0): self
    {
        $this->runAssertion('assertWhere', function () use ($path, $callback, $message) {
            $actual = Arr::get($this->structuredResponse->structured, $path);

            Assert::assertTrue(
                $callback($actual),
                $message ?: "The value at path [{$path}] did not satisfy the callback.",
            );
        }, $weight);

        return $this;
    }

    private function assertArrayStructure(array $structure, mixed $data): void
    {
        Assert::assertIsArray($data);

        foreach ($structure as $key => $value) {
            if (is_array($value) && $key === '*') {
                foreach ($data as $item) {
                    $this->assertArrayStructure($value, $item);
                }
            } elseif (is_array($value)) {
                Assert::assertArrayHasKey($key, $data);
                $this->assertArrayStructure($value, $data[$key]);
            } else {
                Assert::assertArrayHasKey($value, $data);
            }
        }
    }
}
