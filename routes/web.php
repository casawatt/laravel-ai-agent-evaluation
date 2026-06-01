<?php

use Casawatt\LaravelAiAgentEvaluation\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('ai-agent-evaluation', [ReportController::class, 'index'])
    ->name('ai-agent-evaluation.index');

Route::get('ai-agent-evaluation/{runId}', [ReportController::class, 'show'])
    ->where('runId', '[A-Za-z0-9\-T]+')
    ->name('ai-agent-evaluation.show');

Route::delete('ai-agent-evaluation/{runId}', [ReportController::class, 'destroy'])
    ->where('runId', '[A-Za-z0-9\-T]+')
    ->name('ai-agent-evaluation.destroy');
