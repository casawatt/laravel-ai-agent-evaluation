<?php

use Casawatt\LaravelAiAgentEvaluation\CostResolvers\ModelsDevCostResolver;
use Casawatt\LaravelAiAgentEvaluation\CostResolvers\OpenRouterCostResolver;
use Casawatt\LaravelAiAgentEvaluation\CostResolvers\ScalewayCostResolver;

// config for Casawatt/LaravelAiAgentEvaluation

return [

    /*
     * Path where evaluation files are stored.
     */
    'path' => base_path('agent-evaluations'),

    /*
     * Default timeout in seconds for each agent prompt during evaluation.
     */
    'timeout' => 120,

    /*
     * Number of cases to run in parallel. Requires the pcntl extension.
     * Set to 1 for sequential execution. Override per-run with --parallel.
     */
    'parallel' => 1,

    /*
     * Cost resolvers are tried in order to determine pricing for variants
     * that don't have explicit pricing() set. Each class must implement
     * Casawatt\LaravelAiAgentEvaluation\CostResolverInterface.
     */
    'cost_resolvers' => [
        OpenRouterCostResolver::class,
        ScalewayCostResolver::class,
        ModelsDevCostResolver::class,
    ],

    'storage' => [
        'driver' => 'file', // sqlite, file
        'path' => storage_path('ai-agent-evaluation'),
    ],
];
