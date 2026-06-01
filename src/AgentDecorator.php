<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Promptable;
use Stringable;

class AgentDecorator implements Agent, Conversational, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable;

    private readonly TextGenerationOptions $delegateOptions;

    public function __construct(
        private readonly Agent $delegate,
        private readonly ?string $customInstructions = null,
        private readonly ?float $temperature = null,
        private readonly ?float $topP = null,
        private readonly ?int $maxTokens = null,
        private readonly ?int $maxSteps = null,
    ) {
        $this->delegateOptions = TextGenerationOptions::forAgent($delegate);
    }

    public function instructions(): Stringable|string
    {
        return $this->customInstructions ?? $this->delegate->instructions();
    }

    public function temperature(): ?float
    {
        return $this->temperature ?? $this->delegateOptions->temperature;
    }

    public function topP(): ?float
    {
        return $this->topP ?? $this->delegateOptions->topP;
    }

    public function maxTokens(): ?int
    {
        return $this->maxTokens ?? $this->delegateOptions->maxTokens;
    }

    public function maxSteps(): ?int
    {
        return $this->maxSteps ?? $this->delegateOptions->maxSteps;
    }

    public function messages(): iterable
    {
        return $this->delegate instanceof Conversational ? $this->delegate->messages() : [];
    }

    public function tools(): iterable
    {
        return $this->delegate instanceof HasTools ? $this->delegate->tools() : [];
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->delegate instanceof HasStructuredOutput ? $this->delegate->schema($schema) : [];
    }

    public function middleware(): array
    {
        return $this->delegate instanceof HasMiddleware ? $this->delegate->middleware() : [];
    }
}
