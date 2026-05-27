<?php

test('returns 404 for an unknown run id', function () {
    $this->get('/ai-agent-evaluation/unknown-run-id')->assertNotFound();
});

test('the index page renders an empty state when no runs exist', function () {
    $this->get('/ai-agent-evaluation')
        ->assertOk()
        ->assertSee('No runs yet.')
        ->assertSee('php artisan agent-evaluation');
});

test('the index page lists seeded runs newest first with a link to each', function () {
    $older = '2026-01-01T100000-aaaaaaaa';
    $newer = '2026-05-13T143052-bbbbbbbb';

    $this->seedRun($older, [[
        'evaluation' => 'E', 'case' => 'a', 'variant' => 'v', 'status' => 'passed',
    ]]);
    $this->seedRun($newer, [
        ['evaluation' => 'E', 'case' => 'a', 'variant' => 'v', 'status' => 'passed'],
        ['evaluation' => 'E', 'case' => 'b', 'variant' => 'v', 'status' => 'failed'],
    ]);

    $response = $this->get('/ai-agent-evaluation');

    $response->assertOk()
        ->assertSee($older)
        ->assertSee($newer)
        ->assertSee('/ai-agent-evaluation/'.$newer, false)
        ->assertSee('/ai-agent-evaluation/'.$older, false);

    // Newer run should appear before older in the rendered HTML.
    $body = $response->getContent();
    expect(strpos((string) $body, $newer))->toBeLessThan(strpos((string) $body, $older));
});

test('the index page renders a delete button for each run', function () {
    $runId = '2026-05-13T143052-bbbbbbbb';

    $this->seedRun($runId, [[
        'evaluation' => 'E', 'case' => 'a', 'variant' => 'v', 'status' => 'passed',
    ]]);

    $this->get('/ai-agent-evaluation')
        ->assertOk()
        ->assertSee('/ai-agent-evaluation/'.$runId, false)
        ->assertSee('Delete')
        ->assertSee('DELETE', false); // method spoofing field
});

test('deleting a run removes it and redirects to the index', function () {
    $kept = '2026-01-01T100000-aaaaaaaa';
    $deleted = '2026-05-13T143052-bbbbbbbb';

    $this->seedRun($kept, [[
        'evaluation' => 'E', 'case' => 'a', 'variant' => 'v', 'status' => 'passed',
    ]]);
    $this->seedRun($deleted, [[
        'evaluation' => 'E', 'case' => 'b', 'variant' => 'v', 'status' => 'failed',
    ]]);

    $this->delete('/ai-agent-evaluation/'.$deleted)
        ->assertRedirect('/ai-agent-evaluation');

    $this->get('/ai-agent-evaluation')
        ->assertOk()
        ->assertSee($kept)
        ->assertDontSee($deleted);

    $this->get('/ai-agent-evaluation/'.$deleted)->assertNotFound();
});

test('renders the report for a seeded run', function () {
    $runId = '2026-05-13T143052-deadbeef';

    $this->seedRun($runId, [
        [
            'evaluation' => 'SalesCoachEvaluation',
            'case' => 'greet_customer',
            'variant' => 'openai/gpt-4o-mini',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'status' => 'passed',
            'latency_seconds' => 0.42,
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            'prompt_text' => 'How should I greet the customer?',
            'response_text' => 'Hello! How can I help you today?',
            'cost' => 0.0001,
            'score' => [
                'passed_weight' => 1.0,
                'total_weight' => 1.0,
                'assertions' => [
                    ['assertion' => 'contains:Hello', 'passed' => true, 'weight' => 1.0, 'metric' => null, 'message' => null],
                ],
            ],
        ],
        [
            'evaluation' => 'SalesCoachEvaluation',
            'case' => 'greet_customer',
            'variant' => 'anthropic/claude-haiku',
            'provider' => 'anthropic',
            'model' => 'claude-haiku',
            'status' => 'failed',
            'failure_message' => 'Expected greeting not found',
            'latency_seconds' => 1.2,
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 80],
            'response_text' => 'Bonjour client.',
        ],
        [
            'evaluation' => 'SalesCoachEvaluation',
            'case' => 'handle_objection',
            'variant' => 'openai/gpt-4o-mini',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'status' => 'skipped',
            'skip_reason' => 'No fixture available',
        ],
    ]);

    $response = $this->get('/ai-agent-evaluation/'.$runId);

    $response->assertOk()
        ->assertSee($runId)
        ->assertSee('SalesCoachEvaluation')
        ->assertSee('greet_customer')
        ->assertSee('handle_objection')
        ->assertSee('openai/gpt-4o-mini')
        ->assertSee('anthropic/claude-haiku')
        ->assertSee('Hello! How can I help you today?')
        ->assertSee('How should I greet the customer?')
        ->assertSee('Expected greeting not found')
        ->assertSee('No fixture available')
        ->assertSee('PASS')
        ->assertSee('FAIL')
        ->assertSee('SKIP');
});

test('aggregates per-variant metric scores from assertion metric fields', function () {
    $runId = '2026-05-13T143052-fadecafe';

    $assertion = fn (string $metric, bool $passed, float $weight = 1.0) => [
        'assertion' => $metric, 'passed' => $passed, 'weight' => $weight, 'metric' => $metric, 'message' => null,
    ];

    $this->seedRun($runId, [
        [
            'evaluation' => 'MetricsEvaluation',
            'case' => 'case_a',
            'variant' => 'openai/gpt-4o-mini',
            'status' => 'passed',
            'response_text' => 'A',
            'score' => [
                'passed_weight' => 2.0,
                'total_weight' => 2.0,
                'assertions' => [$assertion('safety', true), $assertion('factual', true)],
            ],
        ],
        [
            'evaluation' => 'MetricsEvaluation',
            'case' => 'case_b',
            'variant' => 'openai/gpt-4o-mini',
            'status' => 'failed',
            'response_text' => 'B',
            'score' => [
                'passed_weight' => 1.0,
                'total_weight' => 2.0,
                'assertions' => [$assertion('safety', true), $assertion('factual', false)],
            ],
        ],
    ]);

    $response = $this->get('/ai-agent-evaluation/'.$runId);

    $response->assertOk()
        ->assertSee('Metrics')
        ->assertSee('safety')
        ->assertSee('factual')
        ->assertSee('100%')   // safety: 2/2 passed
        ->assertSee('50%');   // factual: 1/2 passed
});

test('renders empty-row cells when a case is missing for a variant', function () {
    $runId = '2026-05-13T143052-cafef00d';

    $this->seedRun($runId, [
        [
            'evaluation' => 'PartialEvaluation',
            'case' => 'case_a',
            'variant' => 'provider-a/model-a',
            'status' => 'passed',
            'response_text' => 'A',
        ],
        [
            'evaluation' => 'PartialEvaluation',
            'case' => 'case_b',
            'variant' => 'provider-b/model-b',
            'status' => 'passed',
            'response_text' => 'B',
        ],
    ]);

    $this->get('/ai-agent-evaluation/'.$runId)
        ->assertOk()
        ->assertSee('case_a')
        ->assertSee('case_b')
        ->assertSee('provider-a/model-a')
        ->assertSee('provider-b/model-b');
});
