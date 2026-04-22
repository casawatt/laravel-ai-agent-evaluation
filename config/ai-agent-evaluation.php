<?php

// config for Casawatt/LaravelAiAgentEvaluation
return [

    /*
     * Path where evaluation files are stored.
     */
    'path' => base_path('agent-evaluations'),

    /*
     * Default timeout in seconds for each agent prompt during evaluation.
     */
    'timeout' => 60,

    /*
     * Number of cases to run in parallel. Requires the pcntl extension.
     * Set to 1 for sequential execution. Override per-run with --parallel.
     */
    'parallel' => 1,

    'storage' => [
        'driver' => 'sqlite', // sqlite, file
        'path' => storage_path('ai-agent-evaluation'),
    ],
];
