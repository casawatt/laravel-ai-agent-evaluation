<?php

use Casawatt\LaravelAiAgentEvaluation\AgentDecorator;
use Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures\FakeAgent;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

it('exposes the variant generation options to the SDK', function () {
    $decorator = new AgentDecorator(
        new FakeAgent,
        customInstructions: null,
        temperature: 0.2,
        topP: 0.9,
        maxTokens: 512,
        maxSteps: 3,
    );

    expect($decorator->temperature())->toBe(0.2)
        ->and($decorator->topP())->toBe(0.9)
        ->and($decorator->maxTokens())->toBe(512)
        ->and($decorator->maxSteps())->toBe(3);
});

it('returns null options when neither the variant nor the delegate define them', function () {
    $decorator = new AgentDecorator(new FakeAgent);

    expect($decorator->temperature())->toBeNull()
        ->and($decorator->topP())->toBeNull()
        ->and($decorator->maxTokens())->toBeNull()
        ->and($decorator->maxSteps())->toBeNull();
});

it('falls back to the delegate temperature attribute when not overridden', function () {
    $agent = new #[Temperature(0.9)] class implements Agent
    {
        use Promptable;

        public function instructions(): string
        {
            return 'delegate';
        }
    };

    expect((new AgentDecorator($agent))->temperature())->toBe(0.9);
});

it('overrides the delegate temperature when the variant sets it', function () {
    $agent = new #[Temperature(0.9)] class implements Agent
    {
        use Promptable;

        public function instructions(): string
        {
            return 'delegate';
        }
    };

    expect((new AgentDecorator($agent, temperature: 0.1))->temperature())->toBe(0.1);
});

it('uses the delegate instructions when no custom instruction is set', function () {
    $decorator = new AgentDecorator(new FakeAgent);

    expect((string) $decorator->instructions())->toBe('You are a test agent.');
});

it('overrides the delegate instructions with a custom instruction', function () {
    $decorator = new AgentDecorator(new FakeAgent, 'Custom instruction');

    expect((string) $decorator->instructions())->toBe('Custom instruction');
});
