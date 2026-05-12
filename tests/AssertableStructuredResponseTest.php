<?php

use Casawatt\LaravelAiAgentEvaluation\AssertableStructuredResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;

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
    $r = makeStructuredResponse(['name' => 'John', 'age' => 30])
        ->assertStructure(['name', 'age']);
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertStructure with nested keys', function () {
    $r = makeStructuredResponse(['user' => ['name' => 'John', 'email' => 'john@example.com']])
        ->assertStructure(['user' => ['name', 'email']]);
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertStructure with wildcard', function () {
    $r = makeStructuredResponse([
        'items' => [
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ],
    ])->assertStructure([
        'items' => ['*' => ['id', 'name']],
    ]);
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertStructure when key is missing', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertStructure(['name', 'age']);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertEquals (path-based) ---

it('passes assertEquals with dot notation', function () {
    $r = makeStructuredResponse(['user' => ['name' => 'John']])
        ->assertEquals('user.name', 'John');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertEquals for top-level key', function () {
    $r = makeStructuredResponse(['status' => 'active'])
        ->assertEquals('status', 'active');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertEquals when value differs', function () {
    $r = makeStructuredResponse(['status' => 'active'])
        ->assertEquals('status', 'inactive');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('supports non-string expected values in assertEquals', function () {
    $r = makeStructuredResponse(['count' => 42])
        ->assertEquals('count', 42);
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

// --- assertContains / assertNotContains / assertContainsIgnoringCase (path-based) ---

it('passes assertContains at path', function () {
    $r = makeStructuredResponse(['bio' => 'Senior PHP developer'])
        ->assertContains('bio', 'PHP');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertContains when needle missing', function () {
    $r = makeStructuredResponse(['bio' => 'Senior PHP developer'])
        ->assertContains('bio', 'Python');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('records failure for assertContains when value at path is not a string', function () {
    $r = makeStructuredResponse(['count' => 42])
        ->assertContains('count', '42');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertNotContains at path', function () {
    $r = makeStructuredResponse(['bio' => 'Senior PHP developer'])
        ->assertNotContains('bio', 'Python');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertContainsIgnoringCase at path', function () {
    $r = makeStructuredResponse(['bio' => 'Senior PHP developer'])
        ->assertContainsIgnoringCase('bio', 'php');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

// --- assertRegex / assertNotRegex (path-based) ---

it('passes assertRegex at path', function () {
    $r = makeStructuredResponse(['code' => 'PRM12345'])
        ->assertRegex('code', '/^PRM\d+$/');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertRegex when no match', function () {
    $r = makeStructuredResponse(['code' => 'ABC'])
        ->assertRegex('code', '/^PRM\d+$/');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertNotRegex at path', function () {
    $r = makeStructuredResponse(['code' => 'ABC'])
        ->assertNotRegex('code', '/^PRM\d+$/');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

// --- assertStartsWith / assertEndsWith (path-based) ---

it('passes assertStartsWith at path', function () {
    $r = makeStructuredResponse(['url' => 'https://example.com/page'])
        ->assertStartsWith('url', 'https://');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertEndsWith at path', function () {
    $r = makeStructuredResponse(['url' => 'https://example.com/page'])
        ->assertEndsWith('url', '/page');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

// --- assertEmpty / assertNotEmpty (path-based) ---

it('passes assertEmpty at path', function () {
    $r = makeStructuredResponse(['notes' => ''])
        ->assertEmpty('notes');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertNotEmpty at path', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertNotEmpty('name');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertNotEmpty when value at path is empty', function () {
    $r = makeStructuredResponse(['notes' => ''])
        ->assertNotEmpty('notes');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertMinLength / assertMaxLength (path-based) ---

it('passes assertMinLength at path', function () {
    $r = makeStructuredResponse(['name' => 'Hello World'])
        ->assertMinLength('name', 5);
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('passes assertMaxLength at path', function () {
    $r = makeStructuredResponse(['name' => 'Hi'])
        ->assertMaxLength('name', 5);
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

// --- assertHasKey / assertMissingKey ---

it('passes assertHasKey', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertHasKey('name');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertHasKey when missing', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertHasKey('age');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertMissingKey', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertMissingKey('age');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertMissingKey when present', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertMissingKey('name');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertHasKey with dot-notation', function () {
    $r = makeStructuredResponse(['user' => ['address' => ['city' => 'Paris']]])
        ->assertHasKey('user.address.city');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertHasKey with dot-notation when missing', function () {
    $r = makeStructuredResponse(['user' => ['address' => ['city' => 'Paris']]])
        ->assertHasKey('user.address.zip');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

it('passes assertMissingKey with dot-notation', function () {
    $r = makeStructuredResponse(['user' => ['address' => ['city' => 'Paris']]])
        ->assertMissingKey('user.address.zip');
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertMissingKey with dot-notation when present', function () {
    $r = makeStructuredResponse(['user' => ['address' => ['city' => 'Paris']]])
        ->assertMissingKey('user.address.city');
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertCount ---

it('passes assertCount', function () {
    $r = makeStructuredResponse(['a' => 1, 'b' => 2, 'c' => 3])
        ->assertCount(3);
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertCount when wrong', function () {
    $r = makeStructuredResponse(['a' => 1, 'b' => 2])
        ->assertCount(3);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- assertWhere ---

it('passes assertWhere with callback', function () {
    $r = makeStructuredResponse(['score' => 85])
        ->assertWhere('score', fn ($value) => $value >= 80);
    expect($r->getAssertionResults()->first()->passed)->toBeTrue();
});

it('records failure for assertWhere when callback returns false', function () {
    $r = makeStructuredResponse(['score' => 50])
        ->assertWhere('score', fn ($value) => $value >= 80);
    expect($r->getAssertionResults()->first()->passed)->toBeFalse();
});

// --- Chaining ---

it('supports chaining structured assertions', function () {
    $r = makeStructuredResponse([
        'name' => 'John',
        'age' => 30,
        'bio' => 'A developer',
    ])
        ->assertStructure(['name', 'age', 'bio'])
        ->assertEquals('name', 'John')
        ->assertContains('bio', 'developer')
        ->assertHasKey('age')
        ->assertMissingKey('email')
        ->assertCount(3)
        ->assertWhere('age', fn ($v) => $v > 18);
    expect($r->getAssertionResults()->every(fn ($x) => $x->passed))->toBeTrue();
});

// --- Inherited response-level assertions ---

it('can use response-level assertions inherited from base', function () {
    $r = makeStructuredResponse(['name' => 'John'])
        ->assertLatencyBelow(1.0)
        ->assertTokensBelow(1000);
    expect($r->getAssertionResults()->every(fn ($x) => $x->passed))->toBeTrue();
});

// --- Weighted Assertions ---

it('supports weighted structured assertions', function () {
    $response = makeStructuredResponse(['name' => 'John', 'age' => 30]);
    $response
        ->assertEquals('name', 'John', weight: 0.8)
        ->assertEquals('age', 99, weight: 0.2)
        ->assertHasKey('name', weight: 0.5);

    $results = $response->getAssertionResults();
    expect($results)->toHaveCount(3);
    expect($results->where('passed', true)->sum('weight'))->toBe(1.3);
    expect($results->where('passed', false)->sum('weight'))->toBe(0.2);
});
