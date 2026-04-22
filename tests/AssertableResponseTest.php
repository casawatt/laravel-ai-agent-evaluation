<?php

use Casawatt\LaravelAiAgentEvaluation\AssertableResponse;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;

function makeResponse(string $text = 'Hello World', ?Usage $usage = null, array $toolCalls = []): AssertableResponse
{
    $usage ??= new Usage(promptTokens: 100, completionTokens: 50);
    $meta = new Meta(provider: 'openai', model: 'gpt-4o-mini');
    $response = new AgentResponse('test-id', $text, $usage, $meta);

    if ($toolCalls !== []) {
        $response->withToolCallsAndResults(
            toolCalls: collect($toolCalls),
            toolResults: collect(),
        );
    }

    return new AssertableResponse($response, latencySeconds: 0.5);
}

// --- Text Assertions ---

it('passes assertContains when text contains needle', function () {
    $r = makeResponse('Hello World')->assertContains('Hello');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertContains when text does not match', function () {
    $r = makeResponse('Hello World')->assertContains('Goodbye');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertNotContains when text does not contain needle', function () {
    $r = makeResponse('Hello World')->assertNotContains('Goodbye');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertNotContains when text contains needle', function () {
    $r = makeResponse('Hello World')->assertNotContains('Hello');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertContainsIgnoringCase', function () {
    $r = makeResponse('Hello World')->assertContainsIgnoringCase('hello');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertRegex', function () {
    $r = makeResponse('Hello World 123')->assertRegex('/\d+/');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertRegex when no match', function () {
    $r = makeResponse('Hello World')->assertRegex('/\d+/');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertNotRegex', function () {
    $r = makeResponse('Hello World')->assertNotRegex('/\d+/');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertStartsWith', function () {
    makeResponse('Hello World')->assertStartsWith('Hello');
});

it('passes assertEndsWith', function () {
    makeResponse('Hello World')->assertEndsWith('World');
});

it('passes assertExactly', function () {
    makeResponse('Hello')->assertExactly('Hello');
});

it('records failure for assertExactly when text differs', function () {
    $r = makeResponse('Hello')->assertExactly('Goodbye');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertEmpty', function () {
    makeResponse('')->assertEmpty();
});

it('passes assertNotEmpty', function () {
    makeResponse('Hello')->assertNotEmpty();
});

// --- Length Assertions ---

it('passes assertMinLength', function () {
    makeResponse('Hello World')->assertMinLength(5);
});

it('records failure for assertMinLength when too short', function () {
    $r = makeResponse('Hi')->assertMinLength(5);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertMaxLength', function () {
    makeResponse('Hi')->assertMaxLength(5);
});

it('records failure for assertMaxLength when too long', function () {
    $r = makeResponse('Hello World')->assertMaxLength(5);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- Tool Call Assertions ---

it('passes assertToolCalled when tool was called', function () {
    $toolCall = new ToolCall(id: '1', name: 'search', arguments: ['query' => 'test']);
    makeResponse('result', toolCalls: [$toolCall])->assertToolCalled('search');
});

it('records failure for assertToolCalled when tool was not called', function () {
    $r = makeResponse('result')->assertToolCalled('search');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertToolNotCalled', function () {
    makeResponse('result')->assertToolNotCalled('search');
});

it('passes assertToolCalledTimes', function () {
    $toolCalls = [
        new ToolCall(id: '1', name: 'search', arguments: []),
        new ToolCall(id: '2', name: 'search', arguments: []),
    ];
    makeResponse('result', toolCalls: $toolCalls)->assertToolCalledTimes('search', 2);
});

// --- Performance Assertions ---

it('passes assertLatencyBelow', function () {
    makeResponse('Hello')->assertLatencyBelow(1.0);
});

it('records failure for assertLatencyBelow when too slow', function () {
    $r = makeResponse('Hello')->assertLatencyBelow(0.1);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertTokensBelow', function () {
    makeResponse('Hello', new Usage(promptTokens: 50, completionTokens: 30))
        ->assertTokensBelow(100);
});

it('records failure for assertTokensBelow when too many tokens', function () {
    $r = makeResponse('Hello', new Usage(promptTokens: 50, completionTokens: 30))
        ->assertTokensBelow(50);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- Custom Assertion ---

it('passes custom assert', function () {
    makeResponse('Hello World')->assert(fn ($response) => str_contains($response->text, 'World'));
});

it('records failure for custom assert', function () {
    $r = makeResponse('Hello World')->assert(fn ($response) => str_contains($response->text, 'Goodbye'));
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- Chaining ---

it('supports chaining multiple assertions', function () {
    makeResponse('Hello World 123')
        ->assertNotEmpty()
        ->assertContains('Hello')
        ->assertRegex('/\d+/')
        ->assertMinLength(5)
        ->assertLatencyBelow(1.0);
});

// --- Weighted Assertions ---

it('defaults to weight 1.0', function () {
    $r = makeResponse('Hello World')->assertContains('Hello');
    expect($r->getAssertionResults()->first()->weight)->toBe(1.0);
});

it('accepts custom weight', function () {
    $r = makeResponse('Hello World')->assertContains('Hello', weight: 0.5);
    expect($r->getAssertionResults()->first()->weight)->toBe(0.5);
});

it('continues execution after assertion failure', function () {
    $response = makeResponse('Hello World')
        ->assertContains('Goodbye')
        ->assertContains('Hello')
        ->assertContains('Missing');

    $results = $response->getAssertionResults();
    expect($results)->toHaveCount(3);
    expect($results->where('passed', true))->toHaveCount(1);
    expect($results->where('passed', false))->toHaveCount(2);
});

it('computes weighted score across assertions', function () {
    $response = makeResponse('Hello World')
        ->assertContains('Hello', weight: 0.8)
        ->assertContains('Goodbye', weight: 0.2);

    $results = $response->getAssertionResults();
    expect($results->sum('weight'))->toBe(1.0);
    expect($results->where('passed', true)->sum('weight'))->toBe(0.8);
});

it('records all assertions with their results', function () {
    $response = makeResponse('Hello World')
        ->assertNotEmpty()
        ->assertContains('Hello')
        ->assertContains('World');

    $results = $response->getAssertionResults();
    expect($results)->toHaveCount(3);
    expect($results->every(fn ($r) => $r->passed))->toBeTrue();
    expect($results->sum('weight'))->toBe(3.0);
});
