<?php

use Casawatt\LaravelAiAgentEvaluation\EvaluationRunner;
use Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures\FakeAgent;
use Casawatt\LaravelAiAgentEvaluation\Variant;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

beforeEach(function () {
    $this->tempPath = sys_get_temp_dir().'/eval-runner-test-'.uniqid();
    mkdir($this->tempPath, 0755, true);

    writeEvaluation();
});

afterEach(function () {
    File::deleteDirectory($this->tempPath);
});

function writeEvaluation(?string $path = null, ?string $content = null): void
{
    $path ??= test()->tempPath.'/SampleEvaluation.php';
    $content ??= <<<'PHP'
        <?php

        use Laravel\Ai\Enums\Lab;
        use Casawatt\LaravelAiAgentEvaluation\Attributes\EvaluationCase;
        use Casawatt\LaravelAiAgentEvaluation\Evaluation;
        use Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures\FakeAgent;

        return new class extends Evaluation
        {
            protected string $agent = FakeAgent::class;

            public function setUp(): void
            {
                $this->variant(Lab::OpenAI, 'gpt-4o-mini');
                $this->variant(Lab::Anthropic, 'claude-haiku-4-5-20251001');
            }

            #[EvaluationCase]
            public function it_responds(): void
            {
                $this->agent(prompt: 'Hello')
                    ->assertNotEmpty();
            }

            #[EvaluationCase(description: 'Contains greeting')]
            public function it_greets(): void
            {
                $this->agent(prompt: 'Say hello')
                    ->assertContains('hello');
            }

            public function not_a_case(): void
            {
                // Should not be discovered
            }
        };
        PHP;

    file_put_contents($path, $content);
}

function bindFakeAgent(?callable $responseFactory = null): void
{
    $responseFactory ??= function (string $prompt, Lab|array|string|null $provider, ?string $model): AgentResponse {
        $text = match (true) {
            str_contains($prompt, 'Say hello') => 'hello world',
            default => 'Test response',
        };

        return new AgentResponse(
            'inv-123',
            $text,
            new Usage(promptTokens: 50, completionTokens: 25),
            new Meta(
                provider: $provider instanceof Lab ? $provider->value : ($provider ?? 'unknown'),
                model: $model,
            ),
        );
    };

    app()->bind(FakeAgent::class, function () use ($responseFactory) {
        return new class($responseFactory)
        {
            public function __construct(private Closure $responseFactory) {}

            public function prompt(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): AgentResponse
            {
                return ($this->responseFactory)($prompt, $provider, $model);
            }
        };
    });
}

it('discovers evaluation files', function () {
    $runner = new EvaluationRunner($this->tempPath);
    $evaluations = $runner->discover();

    expect($evaluations)->toHaveKey('SampleEvaluation');
});

it('filters evaluations by name', function () {
    $runner = new EvaluationRunner($this->tempPath);

    $found = $runner->discover('Sample');
    expect($found)->toHaveCount(1);

    $notFound = $runner->discover('NonExistent');
    expect($notFound)->toBeEmpty();
});

it('returns empty array for non-existent path', function () {
    $runner = new EvaluationRunner('/non/existent/path');
    expect($runner->discover())->toBeEmpty();
});

it('runs evaluations and collects results', function () {
    bindFakeAgent();

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run();

    expect($suites)->toHaveCount(1);

    $suite = $suites->first();
    // 2 variants x 2 cases = 4 results
    expect($suite->results)->toHaveCount(4);
    expect($suite->allPassed())->toBeTrue();
});

it('records failures when assertions fail', function () {
    bindFakeAgent(fn ($prompt, $provider, $model) => new AgentResponse(
        'inv-123',
        'No greeting here',
        new Usage(promptTokens: 50, completionTokens: 25),
        new Meta(provider: 'openai', model: 'gpt-4o-mini'),
    ));

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run();

    $suite = $suites->first();
    expect($suite->totalFailed())->toBeGreaterThan(0);
    expect($suite->allPassed())->toBeFalse();

    $failures = $suite->results->filter(fn ($r) => $r->failed());
    expect($failures->first()->failureMessage)->toBeString();
});

it('invokes onCaseComplete callback', function () {
    bindFakeAgent();

    $callbackCount = 0;
    $runner = new EvaluationRunner($this->tempPath);
    $runner->run(onCaseComplete: function () use (&$callbackCount) {
        $callbackCount++;
    });

    expect($callbackCount)->toBe(4); // 2 variants x 2 cases
});

it('filters by variant label', function () {
    bindFakeAgent();

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run(variantFilter: 'openai');

    $suite = $suites->first();
    // Only 1 variant x 2 cases = 2 results
    expect($suite->results)->toHaveCount(2);
});

it('captures latency and usage', function () {
    bindFakeAgent();

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run();

    $result = $suites->first()->results->first();

    expect($result->latencySeconds)->toBeGreaterThan(0);
    expect($result->usage)->not->toBeNull();
    expect($result->usage->promptTokens)->toBe(50);
    expect($result->usage->completionTokens)->toBe(25);
});

it('extracts case description from attribute', function () {
    bindFakeAgent();

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run();

    $results = $suites->first()->results;

    $greetCase = $results->firstWhere('caseName', 'it_greets');
    expect($greetCase->caseDescription)->toBe('Contains greeting');

    $respondCase = $results->firstWhere('caseName', 'it_responds');
    expect($respondCase->caseDescription)->toBe('it responds');
});

it('stores variant on result', function () {
    bindFakeAgent();

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run();

    $result = $suites->first()->results->first();

    expect($result->variant)->not->toBeNull();
    expect($result->variantLabel())->toBe('openai/gpt-4o-mini');
});

it('supports custom variant labels', function () {
    writeEvaluation(test()->tempPath.'/LabeledEvaluation.php', <<<'PHP'
        <?php

        use Laravel\Ai\Enums\Lab;
        use Casawatt\LaravelAiAgentEvaluation\Attributes\EvaluationCase;
        use Casawatt\LaravelAiAgentEvaluation\Evaluation;
        use Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures\FakeAgent;

        return new class extends Evaluation
        {
            protected string $agent = FakeAgent::class;

            public function setUp(): void
            {
                $this->variant(Lab::OpenAI, 'gpt-4o-mini')
                    ->label('GPT cheap');
            }

            #[EvaluationCase]
            public function it_works(): void
            {
                $this->agent(prompt: 'Hello')->assertNotEmpty();
            }
        };
        PHP);

    bindFakeAgent();

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run(filter: 'Labeled');

    $result = $suites->first()->results->first();
    expect($result->variantLabel())->toBe('GPT cheap');
});

it('handles skip() in evaluation cases', function () {
    writeEvaluation(test()->tempPath.'/SkipEvaluation.php', <<<'PHP'
        <?php

        use Laravel\Ai\Enums\Lab;
        use Casawatt\LaravelAiAgentEvaluation\Attributes\EvaluationCase;
        use Casawatt\LaravelAiAgentEvaluation\Evaluation;
        use Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures\FakeAgent;

        return new class extends Evaluation
        {
            protected string $agent = FakeAgent::class;

            public function setUp(): void
            {
                $this->variant(Lab::OpenAI, 'gpt-4o-mini');
                $this->variant(Lab::Mistral, 'mistral-small');
            }

            #[EvaluationCase]
            public function always_runs(): void
            {
                $this->agent(prompt: 'Hello')->assertNotEmpty();
            }

            #[EvaluationCase]
            public function skipped_for_mistral(): void
            {
                $this->skipWhen(fn ($v) => $v->provider === Lab::Mistral, 'Mistral unsupported');
                $this->agent(prompt: 'Hello')->assertNotEmpty();
            }
        };
        PHP);

    bindFakeAgent();

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run(filter: 'Skip');

    $suite = $suites->first();
    // 2 variants x 2 cases = 4 results total
    expect($suite->results)->toHaveCount(4);

    // 1 skipped (mistral + skipped_for_mistral)
    $skipped = $suite->results->filter(fn ($r) => $r->skipped());
    expect($skipped)->toHaveCount(1);
    expect($skipped->first()->skipReason)->toBe('Mistral unsupported');

    // Skipped don't count as failures
    expect($suite->allPassed())->toBeTrue();
    expect($suite->totalSkipped())->toBe(1);
    expect($suite->totalPassed())->toBe(3);
});

it('expands cases with #[With] data provider', function () {
    writeEvaluation(test()->tempPath.'/WithEvaluation.php', <<<'PHP'
        <?php

        use Laravel\Ai\Enums\Lab;
        use Casawatt\LaravelAiAgentEvaluation\Attributes\EvaluationCase;
        use Casawatt\LaravelAiAgentEvaluation\Attributes\With;
        use Casawatt\LaravelAiAgentEvaluation\Evaluation;
        use Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures\FakeAgent;

        return new class extends Evaluation
        {
            protected string $agent = FakeAgent::class;

            public function setUp(): void
            {
                $this->variant(Lab::OpenAI, 'gpt-4o-mini');
            }

            public function greetingData(): array
            {
                return [
                    'english' => ['Say hello', 'hello'],
                    'french'  => ['Dis bonjour', 'bonjour'],
                ];
            }

            #[EvaluationCase]
            #[With('greetingData')]
            public function responds_with_greeting(string $prompt, string $expected): void
            {
                $this->agent(prompt: $prompt)
                    ->assertContains($expected);
            }

            #[EvaluationCase]
            public function simple_case(): void
            {
                $this->agent(prompt: 'Hello')->assertNotEmpty();
            }
        };
        PHP);

    bindFakeAgent(function ($prompt, $provider, $model) {
        $text = match (true) {
            str_contains($prompt, 'hello') => 'hello world',
            str_contains($prompt, 'bonjour') => 'bonjour le monde',
            default => 'Test response',
        };

        return new AgentResponse(
            'inv-123', $text,
            new Usage(promptTokens: 50, completionTokens: 25),
            new Meta(provider: 'openai', model: 'gpt-4o-mini'),
        );
    });

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run(filter: 'With');

    $suite = $suites->first();
    // 1 variant x (2 data sets + 1 simple case) = 3 results
    expect($suite->results)->toHaveCount(3);
    expect($suite->allPassed())->toBeTrue();

    $caseNames = $suite->results->pluck('caseName')->all();
    expect($caseNames)->toContain('responds_with_greeting (english)');
    expect($caseNames)->toContain('responds_with_greeting (french)');
    expect($caseNames)->toContain('simple_case');
});

it('loads variant instruction from file:// absolute path', function () {
    $instructionFile = $this->tempPath.'/prompts/strict-coach.md';
    mkdir(dirname($instructionFile), 0755, true);
    file_put_contents($instructionFile, 'You are a strict sales coach.');

    $variant = new Variant(Lab::OpenAI, 'gpt-4o-mini');
    $variant->instruction('file://'.$instructionFile);

    expect($variant->instruction)->toBe('You are a strict sales coach.');
});

it('loads variant instruction from file:// relative path', function () {
    $instructionFile = $this->tempPath.'/prompts/coach.md';
    mkdir(dirname($instructionFile), 0755, true);
    file_put_contents($instructionFile, 'You are a coach.');

    config()->set('ai-agent-evaluation.path', $this->tempPath);

    writeEvaluation($this->tempPath.'/RelativeInstructionEvaluation.php', <<<'PHP'
        <?php

        use Laravel\Ai\Enums\Lab;
        use Casawatt\LaravelAiAgentEvaluation\Attributes\EvaluationCase;
        use Casawatt\LaravelAiAgentEvaluation\Evaluation;
        use Casawatt\LaravelAiAgentEvaluation\Tests\Fixtures\FakeAgent;

        return new class extends Evaluation
        {
            protected string $agent = FakeAgent::class;

            public function setUp(): void
            {
                $this->variant(Lab::OpenAI, 'gpt-4o-mini')
                    ->instruction('file://prompts/coach.md');
            }

            #[EvaluationCase]
            public function it_works(): void
            {
                $this->agent(prompt: 'Hello')->assertNotEmpty();
            }
        };
        PHP);

    bindFakeAgent();

    $runner = new EvaluationRunner($this->tempPath);
    $suites = $runner->run(filter: 'RelativeInstruction');

    $result = $suites->first()->results->first();
    expect($result->variant->instruction)->toBe('You are a coach.');
});

it('throws when file:// instruction file does not exist', function () {
    $variant = new Variant(Lab::OpenAI, 'gpt-4o-mini');
    $variant->instruction('file://nonexistent/file.md');
})->throws(InvalidArgumentException::class, 'Instruction file not found');
