<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->evaluationsPath = sys_get_temp_dir().'/agent-evaluations-test-'.uniqid();
    config()->set('ai-agent-evaluation.path', $this->evaluationsPath);
});

afterEach(function () {
    if (is_dir($this->evaluationsPath)) {
        File::deleteDirectory($this->evaluationsPath);
    }
});

it('creates an evaluation file', function () {
    $this->artisan('make:agent-evaluation', ['name' => 'SaleCoach'])
        ->assertSuccessful();

    expect($this->evaluationsPath.'/SaleCoachEvaluation.php')->toBeFile();

    $content = file_get_contents($this->evaluationsPath.'/SaleCoachEvaluation.php');
    expect($content)->toContain('SaleCoach::class');
    expect($content)->toContain('protected string $agent');
    expect($content)->toContain('function setUp()');
    expect($content)->toContain('$this->variant(');
    expect($content)->toContain('#[EvaluationCase]');
});

it('fails if evaluation already exists', function () {
    $this->artisan('make:agent-evaluation', ['name' => 'SaleCoach'])
        ->assertSuccessful();

    $this->artisan('make:agent-evaluation', ['name' => 'SaleCoach'])
        ->assertFailed();
});
