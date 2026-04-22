<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

class AgentDecorator implements Agent, Conversational, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        private readonly Agent $delegate,
        private readonly string $customInstructions,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->customInstructions;
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
