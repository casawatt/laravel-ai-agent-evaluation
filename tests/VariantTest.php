<?php

use Casawatt\LaravelAiAgentEvaluation\EvaluationResult;
use Casawatt\LaravelAiAgentEvaluation\ResultStatus;
use Casawatt\LaravelAiAgentEvaluation\SuiteBuilder;
use Casawatt\LaravelAiAgentEvaluation\Variant;
use Laravel\Ai\Enums\Lab;

it('sets generation options through fluent setters', function () {
    $variant = (new Variant(Lab::OpenAI, 'gpt-4o-mini'))
        ->temperature(0.2)
        ->topP(0.9)
        ->maxTokens(512)
        ->maxSteps(3);

    expect($variant->temperature)->toBe(0.2)
        ->and($variant->topP)->toBe(0.9)
        ->and($variant->maxTokens)->toBe(512)
        ->and($variant->maxSteps)->toBe(3);
});

it('reports whether any generation option is set', function () {
    $variant = new Variant(Lab::OpenAI, 'gpt-4o-mini');
    expect($variant->hasGenerationOptions())->toBeFalse();

    $variant->temperature(0.7);
    expect($variant->hasGenerationOptions())->toBeTrue();
});

it('persists generation options in the storage array', function () {
    $variant = (new Variant(Lab::OpenAI, 'gpt-4o-mini'))
        ->temperature(0.2)
        ->topP(0.9)
        ->maxTokens(512)
        ->maxSteps(3);

    $data = (new EvaluationResult(
        evaluationName: 'E',
        caseName: 'case',
        caseDescription: 'case',
        variant: $variant,
        status: ResultStatus::Passed,
    ))->toStorageArray();

    expect($data['temperature'])->toBe(0.2)
        ->and($data['top_p'])->toBe(0.9)
        ->and($data['max_tokens'])->toBe(512)
        ->and($data['max_steps'])->toBe(3);
});

it('reconstructs generation options from stored data', function () {
    $result = SuiteBuilder::buildResult([
        'case' => 'case',
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'temperature' => 0.2,
        'top_p' => 0.9,
        'max_tokens' => 512,
        'max_steps' => 3,
        'status' => 'passed',
    ], 'E');

    expect($result->variant->temperature)->toBe(0.2)
        ->and($result->variant->topP)->toBe(0.9)
        ->and($result->variant->maxTokens)->toBe(512)
        ->and($result->variant->maxSteps)->toBe(3);
});
