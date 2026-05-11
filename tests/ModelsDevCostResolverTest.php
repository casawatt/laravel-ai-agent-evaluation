<?php

use Casawatt\LaravelAiAgentEvaluation\CostResolvers\ModelsDevCostResolver;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;

function fakeModelsDevResponse(): void
{
    Http::fake([
        'models.dev/api.json' => Http::response([
            'openai' => [
                'id' => 'openai',
                'models' => [
                    'gpt-4o-mini' => [
                        'id' => 'gpt-4o-mini',
                        'cost' => ['input' => 0.15, 'output' => 0.60],
                    ],
                ],
            ],
            'anthropic' => [
                'id' => 'anthropic',
                'models' => [
                    'claude-sonnet-4-20250514' => [
                        'id' => 'claude-sonnet-4-20250514',
                        'cost' => ['input' => 3.0, 'output' => 15.0],
                    ],
                ],
            ],
            'mistral' => [
                'id' => 'mistral',
                'models' => [
                    'mistral-small' => [
                        'id' => 'mistral-small',
                    ],
                ],
            ],
        ]),
    ]);
}

it('resolves pricing for a known provider and model', function () {
    fakeModelsDevResponse();

    $resolver = new ModelsDevCostResolver;
    $price = $resolver->resolve(Lab::OpenAI, 'gpt-4o-mini');

    expect($price)->not->toBeNull();
    // models.dev pricing is per 1K tokens, so 0.15/1K = 150/1M
    expect($price->inputPerMillion)->toBe(150.0);
    expect($price->outputPerMillion)->toBe(600.0);
});

it('resolves pricing for Anthropic models', function () {
    fakeModelsDevResponse();

    $resolver = new ModelsDevCostResolver;
    $price = $resolver->resolve(Lab::Anthropic, 'claude-sonnet-4-20250514');

    expect($price)->not->toBeNull();
    expect($price->inputPerMillion)->toBe(3000.0);
    expect($price->outputPerMillion)->toBe(15000.0);
});

it('returns null for unknown provider', function () {
    fakeModelsDevResponse();

    $resolver = new ModelsDevCostResolver;

    expect($resolver->resolve('scaleway', 'some-model'))->toBeNull();
});

it('returns null for unknown model', function () {
    fakeModelsDevResponse();

    $resolver = new ModelsDevCostResolver;

    expect($resolver->resolve(Lab::OpenAI, 'unknown-model'))->toBeNull();
});

it('returns null when model has no cost data', function () {
    fakeModelsDevResponse();

    $resolver = new ModelsDevCostResolver;

    expect($resolver->resolve(Lab::Mistral, 'mistral-small'))->toBeNull();
});

it('accepts string provider', function () {
    fakeModelsDevResponse();

    $resolver = new ModelsDevCostResolver;
    $price = $resolver->resolve('openai', 'gpt-4o-mini');

    expect($price)->not->toBeNull();
    expect($price->inputPerMillion)->toBe(150.0);
});

it('caches API response across multiple resolve calls', function () {
    fakeModelsDevResponse();

    $resolver = new ModelsDevCostResolver;
    $resolver->resolve(Lab::OpenAI, 'gpt-4o-mini');
    $resolver->resolve(Lab::Anthropic, 'claude-sonnet-4-20250514');

    Http::assertSentCount(1);
});
