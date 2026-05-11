<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Laravel\Ai\Enums\Lab;

class Variant
{
    public ?string $label;

    public ?string $instruction = null;

    public ?Price $price = null;

    public function __construct(
        public readonly Lab|string $provider,
        public readonly string $model,
        ?string $label = null,
    ) {
        $this->label = $label ?? ($provider instanceof Lab ? $provider->value : $provider).'/'.$model;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function instruction(string $instruction): self
    {
        if (str_starts_with($instruction, 'file://')) {
            $instruction = $this->resolveFileInstruction(substr($instruction, 7));
        }

        $this->instruction = $instruction;

        return $this;
    }

    private function resolveFileInstruction(string $path): string
    {
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        $basePath = config('ai-agent-evaluation.path', base_path('agent-evaluations'));
        $resolved = $basePath.'/'.$path;

        if (! file_exists($resolved)) {
            throw new \InvalidArgumentException("Instruction file not found: {$path}");
        }

        return file_get_contents($resolved);
    }

    public function pricing(float $inputPerMillion, float $outputPerMillion): self
    {
        $this->price = new Price($inputPerMillion, $outputPerMillion);

        return $this;
    }

    public function hasPricing(): bool
    {
        return $this->price !== null;
    }

    public function providerValue(): string
    {
        return $this->provider instanceof Lab ? $this->provider->value : $this->provider;
    }
}
