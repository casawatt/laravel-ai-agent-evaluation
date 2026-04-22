<?php

use Casawatt\LaravelAiAgentEvaluation\EvaluationResult;
use Casawatt\LaravelAiAgentEvaluation\EvaluationSuite;
use Casawatt\LaravelAiAgentEvaluation\ResultStatus;
use Casawatt\LaravelAiAgentEvaluation\Variant;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\Data\Usage;

function makeSuiteWithResults(): EvaluationSuite
{
    $suite = new EvaluationSuite('TestEvaluation', 'App\\Agent');

    $openai = new Variant(Lab::OpenAI, 'gpt-4o-mini');
    $anthropic = new Variant(Lab::Anthropic, 'claude-haiku-4-5-20251001');

    $suite->add(new EvaluationResult(
        evaluationName: 'TestEvaluation',
        caseName: 'case_one',
        caseDescription: 'case one',
        variant: $openai,
        status: ResultStatus::Passed,
        latencySeconds: 0.5,
        usage: new Usage(promptTokens: 100, completionTokens: 50),
    ));

    $suite->add(new EvaluationResult(
        evaluationName: 'TestEvaluation',
        caseName: 'case_two',
        caseDescription: 'case two',
        variant: $openai,
        status: ResultStatus::Failed,
        failureMessage: 'Expected contains "hello"',
        latencySeconds: 0.3,
        usage: new Usage(promptTokens: 80, completionTokens: 40),
    ));

    $suite->add(new EvaluationResult(
        evaluationName: 'TestEvaluation',
        caseName: 'case_one',
        caseDescription: 'case one',
        variant: $anthropic,
        status: ResultStatus::Passed,
        latencySeconds: 0.7,
        usage: new Usage(promptTokens: 90, completionTokens: 45),
    ));

    $suite->add(new EvaluationResult(
        evaluationName: 'TestEvaluation',
        caseName: 'case_two',
        caseDescription: 'case two',
        variant: $anthropic,
        status: ResultStatus::Passed,
        latencySeconds: 0.4,
        usage: new Usage(promptTokens: 85, completionTokens: 42),
    ));

    return $suite;
}

it('counts passed and failed results', function () {
    $suite = makeSuiteWithResults();

    expect($suite->totalPassed())->toBe(3);
    expect($suite->totalFailed())->toBe(1);
    expect($suite->allPassed())->toBeFalse();
});

it('generates provider summaries', function () {
    $suite = makeSuiteWithResults();
    $summaries = $suite->providerSummaries();

    expect($summaries)->toHaveCount(2);

    $openai = $summaries->get('openai/gpt-4o-mini');
    expect($openai['passed'])->toBe(1);
    expect($openai['failed'])->toBe(1);
    expect($openai['total'])->toBe(2);
    expect($openai['pass_rate'])->toBe(0.5);
    expect($openai['total_prompt_tokens'])->toBe(180);
    expect($openai['total_completion_tokens'])->toBe(90);

    $anthropic = $summaries->get('anthropic/claude-haiku-4-5-20251001');
    expect($anthropic['passed'])->toBe(2);
    expect($anthropic['failed'])->toBe(0);
    expect($anthropic['pass_rate'])->toEqual(1.0);
});

it('reports all passed when no failures', function () {
    $suite = new EvaluationSuite('TestEvaluation', 'App\\Agent');
    $variant = new Variant(Lab::OpenAI, 'gpt-4o-mini');

    $suite->add(new EvaluationResult(
        evaluationName: 'TestEvaluation',
        caseName: 'case_one',
        caseDescription: 'case one',
        variant: $variant,
        status: ResultStatus::Passed,
        latencySeconds: 0.5,
        usage: new Usage(promptTokens: 100, completionTokens: 50),
    ));

    expect($suite->allPassed())->toBeTrue();
});
