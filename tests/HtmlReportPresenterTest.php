<?php

use Casawatt\LaravelAiAgentEvaluation\Reporter\HtmlReportPresenter;

it('averages tokens and cost per variant', function () {
    $rows = [
        'E::case_a::openai/gpt-4o-mini' => [
            'evaluation' => 'E',
            'case' => 'case_a',
            'variant' => 'openai/gpt-4o-mini',
            'status' => 'passed',
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            'cost' => 0.001,
        ],
        'E::case_b::openai/gpt-4o-mini' => [
            'evaluation' => 'E',
            'case' => 'case_b',
            'variant' => 'openai/gpt-4o-mini',
            'status' => 'passed',
            'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 100],
            'cost' => 0.003,
        ],
    ];

    $totals = (new HtmlReportPresenter)->present($rows)[0]['totals']['openai/gpt-4o-mini'];

    // (150 + 300) / 2
    expect($totals['avg_tokens'])->toBe(225.0)
        // (0.001 + 0.003) / 2
        ->and($totals['avg_cost'])->toBe(0.002);
});

it('reports null averages when a variant has no usage or cost data', function () {
    $rows = [
        'E::case_a::openai/gpt-4o-mini' => [
            'evaluation' => 'E',
            'case' => 'case_a',
            'variant' => 'openai/gpt-4o-mini',
            'status' => 'error',
        ],
    ];

    $totals = (new HtmlReportPresenter)->present($rows)[0]['totals']['openai/gpt-4o-mini'];

    expect($totals['avg_tokens'])->toBeNull()
        ->and($totals['avg_cost'])->toBeNull();
});
