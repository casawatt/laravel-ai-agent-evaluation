<?php

use Casawatt\LaravelAiAgentEvaluation\CostResolvers\OpenRouterCostResolver;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;

function fakeOpenRouterResponse(): void
{
    Http::fake([
        'openrouter.ai/api/v1/models' => Http::response([
            'data' => [
                [
                    'id' => 'openai/gpt-4o-mini',
                    'pricing' => [
                        'prompt' => '0.00000015',
                        'completion' => '0.0000006',
                    ],
                ],
                [
                    'id' => 'anthropic/claude-sonnet-4-20250514',
                    'pricing' => [
                        'prompt' => '0.000003',
                        'completion' => '0.000015',
                    ],
                ],
                [
                    'id' => 'google/gemma-3-27b-it:free',
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
            ],
        ]),
    ]);
}

it('resolves pricing for an OpenRouter model', function () {
    fakeOpenRouterResponse();

    $resolver = new OpenRouterCostResolver;
    $price = $resolver->resolve(Lab::OpenRouter, 'openai/gpt-4o-mini');

    expect($price)->not->toBeNull();
    expect($price->inputPerMillion)->toBe(0.15);
    expect($price->outputPerMillion)->toBe(0.6);
});

it('converts per-token pricing to per-million', function () {
    fakeOpenRouterResponse();

    $resolver = new OpenRouterCostResolver;
    $price = $resolver->resolve(Lab::OpenRouter, 'anthropic/claude-sonnet-4-20250514');

    expect($price)->not->toBeNull();
    expect($price->inputPerMillion)->toBe(3.0);
    expect($price->outputPerMillion)->toBe(15.0);
});

it('returns null for non-OpenRouter provider', function () {
    fakeOpenRouterResponse();

    $resolver = new OpenRouterCostResolver;

    expect($resolver->resolve(Lab::OpenAI, 'gpt-4o-mini'))->toBeNull();
    expect($resolver->resolve(Lab::Anthropic, 'claude-sonnet-4-20250514'))->toBeNull();
    expect($resolver->resolve('scaleway', 'gpt-oss-120b'))->toBeNull();

    Http::assertNothingSent();
});

it('returns null for unknown model', function () {
    fakeOpenRouterResponse();

    $resolver = new OpenRouterCostResolver;
    $price = $resolver->resolve(Lab::OpenRouter, 'unknown/model');

    expect($price)->toBeNull();
});

it('handles free models with zero pricing', function () {
    fakeOpenRouterResponse();

    $resolver = new OpenRouterCostResolver;
    $price = $resolver->resolve(Lab::OpenRouter, 'google/gemma-3-27b-it:free');

    expect($price)->not->toBeNull();
    expect($price->inputPerMillion)->toBe(0.0);
    expect($price->outputPerMillion)->toBe(0.0);
});

it('accepts string provider', function () {
    fakeOpenRouterResponse();

    $resolver = new OpenRouterCostResolver;
    $price = $resolver->resolve('openrouter', 'openai/gpt-4o-mini');

    expect($price)->not->toBeNull();
    expect($price->inputPerMillion)->toBe(0.15);
});

it('caches API response across multiple resolve calls', function () {
    fakeOpenRouterResponse();

    $resolver = new OpenRouterCostResolver;
    $resolver->resolve(Lab::OpenRouter, 'openai/gpt-4o-mini');
    $resolver->resolve(Lab::OpenRouter, 'anthropic/claude-sonnet-4-20250514');

    Http::assertSentCount(1);
});
