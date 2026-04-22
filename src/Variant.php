<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Laravel\Ai\Enums\Lab;

class Variant
{
    public ?string $label;

    public ?string $instruction = null;

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
        $this->instruction = $instruction;

        return $this;
    }

    public function providerValue(): string
    {
        return $this->provider instanceof Lab ? $this->provider->value : $this->provider;
    }
}
