<?php

namespace Casawatt\LaravelAiAgentEvaluation;

use Casawatt\LaravelAiAgentEvaluation\Commands\MakeAgentEvaluationCommand;
use Casawatt\LaravelAiAgentEvaluation\Commands\RunAgentEvaluationCommand;
use Casawatt\LaravelAiAgentEvaluation\Storage\FileStorage;
use Casawatt\LaravelAiAgentEvaluation\Storage\SqliteStorage;
use Casawatt\LaravelAiAgentEvaluation\Storage\StorageInterface;
use Illuminate\Http\Client\RequestException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAiAgentEvaluationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ai-agent-evaluation')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                MakeAgentEvaluationCommand::class,
                RunAgentEvaluationCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(StorageInterface::class, function () {
            $path = config('ai-agent-evaluation.storage.path', storage_path('ai-agent-evaluation'));

            return match (config('ai-agent-evaluation.storage.driver', 'file')) {
                'sqlite' => new SqliteStorage($path.'/evaluations.sqlite'),
                default => new FileStorage($path),
            };
        });
    }

    public function packageBooted(): void
    {
        if ($this->app->environment('local') || config('app.debug')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $truncateAt = config('ai-agent-evaluation.truncate_errors_at');

        if ($truncateAt === false) {
            RequestException::dontTruncate();
        } elseif (is_int($truncateAt)) {
            RequestException::truncateAt($truncateAt);
        }
    }
}
