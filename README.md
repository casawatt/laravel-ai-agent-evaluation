# Laravel AI Agent Evaluation

Evaluate your [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk) agents across multiple providers and models. Compare performance, accuracy, and cost — like [Promptfoo](https://www.promptfoo.dev/) but for Laravel.

## Installation

```bash
composer require casawatt/laravel-ai-agent-evaluation
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-ai-agent-evaluation-config"
```

## Quick Start

### 1. Create an evaluation

```bash
php artisan make:agent-evaluation SaleCoach
```

This creates:

```
agent-evaluations/
    SaleCoachEvaluation.php
    results/
        .gitignore
```

### 2. Define your evaluation

Edit `agent-evaluations/SaleCoachEvaluation.php`:

```php
<?php

use Laravel\Ai\Enums\Lab;
use Opousset\LaravelAiAgentEvaluation\Attributes\EvaluationCase;
use Opousset\LaravelAiAgentEvaluation\Evaluation;

return new class extends Evaluation
{
    protected string $agent = \App\Ai\Agents\SaleCoach::class;

    public function setUp(): void
    {
        $this->variant(Lab::Mistral, 'mistral-small-3.2-24b-instruct');
        $this->variant(Lab::OpenRouter, 'google/gemma-3-27b-it');
        $this->variant(Lab::OpenRouter, 'openai/gpt-oss-120b');
        $this->variant('scaleway', 'gpt-oss-120b');
    }

    #[EvaluationCase]
    public function contains_hello(): void
    {
        $this->agent(prompt: 'Say hello to the user')
            ->assertContains('hello');
    }

    #[EvaluationCase(description: 'Handles file attachments')]
    public function with_attachments(): void
    {
        $this->agent(
            prompt: 'Summarize this document',
            attachments: [
                'path/to/document.pdf',
                'https://example.com/data.csv',
            ],
        )->assertNotEmpty()
          ->assertMinLength(50);
    }
};
```

### 3. Run evaluations

```bash
php artisan agent-evaluation
```

Each case is run against every variant. Results are displayed in the console and saved as JSON.

## Variants

Variants define the configurations to test against. Configure them in `setUp()`:

```php
public function setUp(): void
{
    $this->variant(Lab::OpenAI, 'gpt-4o-mini');
    $this->variant(Lab::Anthropic, 'claude-sonnet-4-20250514')
        ->label('Sonnet');
}
```

Each variant can have a custom **label** (used in the results table) and a custom **instruction** (overrides the agent's default system prompt):

```php
public function setUp(): void
{
    $this->variant(Lab::OpenAI, 'gpt-4o-mini')
        ->label('Default instructions');

    $this->variant(Lab::OpenAI, 'gpt-4o-mini')
        ->label('Strict coach')
        ->instruction('You are a strict sales coach. Never offer discounts.');

    $this->variant(Lab::OpenAI, 'gpt-4o-mini')
        ->label('Lenient coach')
        ->instruction('You are a lenient sales coach. Offer discounts freely.');
}
```

This lets you compare the **same model with different instructions** — useful for prompt engineering and evaluating system prompt variations.

Custom providers (not in the `Lab` enum) work as long as they are configured in your `config/ai.php`.

## Assertions

All assertions are chainable. The package returns an `AssertableResponse` for text agents and an `AssertableStructuredResponse` for agents implementing `HasStructuredOutput` — the correct type is detected automatically.

### Text (AssertableResponse)

| Method | Description |
|---|---|
| `assertContains(string $needle)` | Response contains the string |
| `assertNotContains(string $needle)` | Response does not contain the string |
| `assertContainsIgnoringCase(string $needle)` | Case-insensitive contains |
| `assertRegex(string $pattern)` | Response matches the regex |
| `assertNotRegex(string $pattern)` | Response does not match the regex |
| `assertStartsWith(string $prefix)` | Response starts with the string |
| `assertEndsWith(string $suffix)` | Response ends with the string |
| `assertExactly(string $expected)` | Response equals the string exactly |
| `assertEmpty()` | Response is empty |
| `assertNotEmpty()` | Response is not empty |
| `assertMinLength(int $min)` | Response has at least `$min` characters |
| `assertMaxLength(int $max)` | Response has at most `$max` characters |

### Structured Data (AssertableStructuredResponse)

Available automatically when your agent implements `HasStructuredOutput`. These work directly on the parsed `$response->structured` array — no JSON parsing needed.

| Method | Description |
|---|---|
| `assertStructure(array $structure)` | Validates nested key structure (supports `*` wildcards) |
| `assertPath(string $path, mixed $expected)` | Value at dot-notation path equals expected |
| `assertPathContains(string $path, string $needle)` | String value at path contains the needle |
| `assertHasKey(string $key)` | Top-level key exists |
| `assertMissingKey(string $key)` | Top-level key does not exist |
| `assertCount(int $count)` | Top-level array has N entries |
| `assertWhere(string $path, callable $callback)` | Value at path satisfies callback |

```php
$this->agent(prompt: 'Describe the product')
    ->assertStructure(['name', 'price', 'tags' => ['*' => ['label']]])
    ->assertPath('name', 'Widget')
    ->assertPathContains('name', 'Wid')
    ->assertHasKey('price')
    ->assertMissingKey('deleted_at')
    ->assertCount(3)
    ->assertWhere('price', fn ($v) => $v > 0 && $v < 1000);
```

### Tool Calls

| Method | Description |
|---|---|
| `assertToolCalled(string $toolName)` | The tool was called during the response |
| `assertToolNotCalled(string $toolName)` | The tool was not called |
| `assertToolCalledTimes(string $toolName, int $times)` | The tool was called exactly N times |

### Performance

| Method | Description |
|---|---|
| `assertLatencyBelow(float $maxSeconds)` | Response latency is below the threshold |
| `assertTokensBelow(int $maxTokens)` | Total tokens (input + output) is below the threshold |

### Custom

```php
->assert(fn ($response) => str_word_count($response->text) > 10, 'Expected more than 10 words')
```

## Weighted Assertions

Every assertion has a `weight` parameter (default `1.0`). Assertions never throw — failures are recorded and execution continues, so all assertions in a case always run. The weight is a float between 0 and 1 that indicates the assertion's importance:

- `1.0` — full importance (default)
- `0.5` — half as important
- `0.1` — nice-to-have

```php
#[EvaluationCase]
public function evaluates_response_quality(): void
{
    $this->agent(prompt: 'What is the capital of France?')
        ->assertContains('Paris')                       // weight: 1.0 (default)
        ->assertNotContains('London')                   // weight: 1.0 (default)
        ->assertMaxLength(200, weight: 0.3);            // less important
}
```

The variant summary table shows a **Score** column with weighted percentages:

```
| Variant      | Results       | Score              | Avg Latency | Tokens In | Tokens Out |
|--------------|---------------|--------------------|-------------|-----------|------------|
| openai/gpt4  | 3/4 (75.0%)   | 2.3/3.3 (69.7%)   | 320ms       | 200       | 3,000      |
| mistral/sm   | 2/4 (50.0%)   | 1.3/3.3 (39.4%)   | 210ms       | 180       | 2,500      |
```

## Data Providers

Use `#[With('methodName')]` to feed multiple data sets into a single case — like PHPUnit's `#[DataProvider]`. The method can load data from anywhere: arrays, models, files, APIs.

```php
use Opousset\LaravelAiAgentEvaluation\Attributes\With;

#[EvaluationCase]
#[With('capitalCities')]
public function knows_capital(string $country, string $capital): void
{
    $this->agent(prompt: "What is the capital of {$country}?")
        ->assertContains($capital);
}

public function capitalCities(): array
{
    return [
        'france'  => ['France', 'Paris'],
        'germany' => ['Germany', 'Berlin'],
        'japan'   => ['Japan', 'Tokyo'],
    ];
}
```

Each data set becomes a separate row in the results: `knows_capital (france)`, `knows_capital (germany)`, etc. The keys are used as labels.

The provider method can return any data — query a database, read a CSV, call an API:

```php
public function customerQuestions(): array
{
    return Customer::query()
        ->where('type', 'test')
        ->get()
        ->mapWithKeys(fn ($c) => [$c->name => [$c->question, $c->expected_answer]])
        ->all();
}
```

## Skipping Cases

Some providers or models may not support certain features (e.g. tool use). You can skip cases conditionally using `skip()` or `skipWhen()`:

```php
use Opousset\LaravelAiAgentEvaluation\Variant;

#[EvaluationCase]
public function uses_tools(): void
{
    $this->skipWhen(
        fn (Variant $v) => $v->provider === Lab::Mistral,
        'Mistral does not support tools',
    );

    $this->agent(prompt: 'Search for restaurants nearby')
        ->assertToolCalled('search');
}
```

`skipWhen()` accepts a boolean or a callable that receives the current `Variant`. You can also call `skip()` to unconditionally skip:

```php
#[EvaluationCase]
public function not_ready_yet(): void
{
    $this->skip('Work in progress');
}
```

Skipped cases show `S` in the progress output and `SKIP` in the test matrix. They do not count as failures.

## Parallel Execution

By default, cases run sequentially. Use `--parallel` to run multiple cases concurrently using [spatie/fork](https://github.com/spatie/fork) (requires the `pcntl` extension):

```bash
# Run 4 cases in parallel
php artisan agent-evaluation --parallel=4
```

Each case runs in its own forked process with a fresh evaluation instance — no shared state. Results are persisted to storage by the parent process after each child completes.

You can set a default in `config/ai-agent-evaluation.php`:

```php
'parallel' => 4,
```

The `--parallel` CLI option overrides the config value. Set to `1` for sequential execution.

## Command Options

```bash
# Run all evaluations
php artisan agent-evaluation

# Filter by evaluation name
php artisan agent-evaluation --filter=SaleCoach

# Filter by variant label
php artisan agent-evaluation --variant=openai

# Run cases in parallel (requires pcntl)
php artisan agent-evaluation --parallel=4

# Output raw JSON to stdout
php artisan agent-evaluation --json

# Resume an interrupted run (skip already-run cases)
php artisan agent-evaluation --resume

# Retry only errors from the latest run (API failures, timeouts)
php artisan agent-evaluation --retry-errors
```

## Storage

Results are persisted to a SQLite database after each case completes — if the process is killed, all completed results are preserved. The database is stored at `storage/ai-agent-evaluation/evaluations.sqlite` by default.

```php
// config/ai-agent-evaluation.php
'storage' => [
    'enabled' => true,
    'driver' => 'sqlite',
    'path' => storage_path('ai-agent-evaluation'),
],
```

The package uses raw PDO with WAL mode — no Laravel database connection required. Safe for concurrent writes.

## Resume and Retry

**`--resume`** — Loads the latest run from storage and skips all case+variant combos that already have results. Only missing cases are executed. Useful when a run was interrupted.

**`--retry-errors`** — Like resume, but re-executes cases with `error` status (API failures, timeouts). Passed/failed/skipped results are kept as-is.

Each result has a `status`: `passed`, `failed`, `skipped`, or `error`. Assertion failures are `failed`; exceptions are `error`.

## Results

Console output includes two tables:

**Test Matrix** — each case x each variant with PASS/FAIL/ERROR/SKIP, latency, and token count.

**Variant Summary** — aggregated pass rate, average latency, and total tokens per variant.

## Testing

```bash
composer test
```

## Credits

- [Olivier Pousset](https://github.com/opousset)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
