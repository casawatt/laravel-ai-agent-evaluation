<?php

use Casawatt\LaravelAiAgentEvaluation\Tests\Http\HttpTestCase;
use Casawatt\LaravelAiAgentEvaluation\Tests\Http\ProductionTestCase;
use Casawatt\LaravelAiAgentEvaluation\Tests\TestCase;

uses(TestCase::class)->in(
    'AgentDecoratorTest.php',
    'ArchTest.php',
    'AssertableResponseTest.php',
    'AssertableStructuredResponseTest.php',
    'EvaluationRunnerTest.php',
    'EvaluationSuiteTest.php',
    'HtmlReportPresenterTest.php',
    'MakeAgentEvaluationCommandTest.php',
    'ModelsDevCostResolverTest.php',
    'OpenRouterCostResolverTest.php',
    'StorageTest.php',
    'TruncateErrorsConfigTest.php',
    'VariantTest.php',
);

uses(HttpTestCase::class)->in('Http/ReportControllerTest.php');
uses(ProductionTestCase::class)->in('Http/RouteGateTest.php');
