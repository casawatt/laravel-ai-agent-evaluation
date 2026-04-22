<?php

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Casawatt\LaravelAiAgentEvaluation\AssertableStructuredResponse;

function makeStructuredResponse(array $structured, ?Usage $usage = null): AssertableStructuredResponse
{
    $usage ??= new Usage(promptTokens: 100, completionTokens: 50);
    $meta = new Meta(provider: 'openai', model: 'gpt-4o-mini');
    $text = json_encode($structured);
    $response = new StructuredAgentResponse('test-id', $structured, $text, $usage, $meta);

    return new AssertableStructuredResponse($response, latencySeconds: 0.5);
}

// --- assertStructure ---

it('passes assertStructure with flat keys', function () {
    makeStructuredResponse(['name' => 'John', 'age' => 30])
        ->assertStructure(['name', 'age']);
});

it('passes assertStructure with nested keys', function () {
    makeStructuredResponse(['user' => ['name' => 'John', 'email' => 'john@example.com']])
        ->assertStructure(['user' => ['name', 'email']]);
});

it('passes assertStructure with wildcard', function () {
    makeStructuredResponse([
        'items' => [
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ],
    ])->assertStructure([
        'items' => ['*' => ['id', 'name']],
    ]);
});

it('records failure for assertStructure when key is missing', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertStructure(['name', 'age']);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertPath ---

it('passes assertPath with dot notation', function () {
    makeStructuredResponse(['user' => ['name' => 'John']])
        ->assertPath('user.name', 'John');
});

it('passes assertPath for top-level key', function () {
    makeStructuredResponse(['status' => 'active'])
        ->assertPath('status', 'active');
});

it('records failure for assertPath when value differs', function () {
    $r = makeStructuredResponse(['status' => 'active'])
        ->assertPath('status', 'inactive');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertPathContains ---

it('passes assertPathContains', function () {
    makeStructuredResponse(['bio' => 'Senior PHP developer'])
        ->assertPathContains('bio', 'PHP');
});

it('records failure for assertPathContains when not found', function () {
    $r = makeStructuredResponse(['bio' => 'Senior PHP developer'])
        ->assertPathContains('bio', 'Python');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('records failure for assertPathContains when value is not a string', function () {
    $r = makeStructuredResponse(['count' => 42])
        ->assertPathContains('count', '42');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertHasKey / assertMissingKey ---

it('passes assertHasKey', function () {
    makeStructuredResponse(['name' => 'John'])
        ->assertHasKey('name');
});

it('records failure for assertHasKey when missing', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertHasKey('age');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertMissingKey', function () {
    makeStructuredResponse(['name' => 'John'])
        ->assertMissingKey('age');
});

it('records failure for assertMissingKey when present', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertMissingKey('name');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertCount ---

it('passes assertCount', function () {
    makeStructuredResponse(['a' => 1, 'b' => 2, 'c' => 3])
        ->assertCount(3);
});

it('records failure for assertCount when wrong', function () {
    $r = makeStructuredResponse(['a' => 1, 'b' => 2])
        ->assertCount(3);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertWhere ---

it('passes assertWhere with callback', function () {
    makeStructuredResponse(['score' => 85])
        ->assertWhere('score', fn ($value) => $value >= 80);
});

it('records failure for assertWhere when callback returns false', function () {
    $r = makeStructuredResponse(['score' => 50])
        ->assertWhere('score', fn ($value) => $value >= 80);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- Chaining ---

it('supports chaining structured assertions', function () {
    makeStructuredResponse([
        'name' => 'John',
        'age' => 30,
        'bio' => 'A developer',
    ])
        ->assertStructure(['name', 'age', 'bio'])
        ->assertPath('name', 'John')
        ->assertPathContains('bio', 'developer')
        ->assertHasKey('age')
        ->assertMissingKey('email')
        ->assertCount(3)
        ->assertWhere('age', fn ($v) => $v > 18);
});

// --- Inherits parent assertions ---

it('can use text assertions from parent', function () {
    makeStructuredResponse(['name' => 'John'])
        ->assertNotEmpty()
        ->assertLatencyBelow(1.0);
});

// --- Weighted Assertions ---

it('supports weighted structured assertions', function () {
    $response = makeStructuredResponse(['name' => 'John', 'age' => 30]);
    $response
        ->assertPath('name', 'John', weight: 0.8)
        ->assertPath('age', 99, weight: 0.2)
        ->assertHasKey('name', weight: 0.5);

    $results = $response->getAssertionResults();
    expect($results)->toHaveCount(3);
    expect($results->where('passed', true)->sum('weight'))->toBe(1.3);
    expect($results->where('passed', false)->sum('weight'))->toBe(0.2);
});
