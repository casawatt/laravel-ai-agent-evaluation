<?php

use Casawatt\LaravelAiAgentEvaluation\AgentDecorator;
use Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures\FakeAgent;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
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

it('forwards the delegate provider options', function () {
    $agent = new class implements Agent, HasProviderOptions
    {
        use Promptable;

        public function instructions(): string
        {
            return 'delegate';
        }

        public function providerOptions(Lab|string $provider): array
        {
            return ['output_config' => ['effort' => 'low'], 'provider' => $provider instanceof Lab ? $provider->value : $provider];
        }
    };

    expect((new AgentDecorator($agent))->providerOptions(Lab::Anthropic))
        ->toBe(['output_config' => ['effort' => 'low'], 'provider' => 'anthropic']);
});

it('lets the variant provider options override the delegate ones', function () {
    $agent = new class implements Agent, HasProviderOptions
    {
        use Promptable;

        public function instructions(): string
        {
            return 'delegate';
        }

        public function providerOptions(Lab|string $provider): array
        {
            return ['output_config' => ['effort' => 'low', 'format' => 'kept']];
        }
    };

    $decorator = new AgentDecorator($agent, providerOptions: ['output_config' => ['effort' => 'medium']]);

    expect($decorator->providerOptions(Lab::Anthropic))
        ->toBe(['output_config' => ['effort' => 'medium', 'format' => 'kept']]);
});

it('returns only the variant provider options when the delegate has none', function () {
    $decorator = new AgentDecorator(new FakeAgent, providerOptions: ['output_config' => ['effort' => 'medium']]);

    expect($decorator->providerOptions(Lab::Anthropic))->toBe(['output_config' => ['effort' => 'medium']]);
});
