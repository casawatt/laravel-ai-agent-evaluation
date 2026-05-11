<?php

namespace Casawatt\LaravelAiAgentEvaluation\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeAgentEvaluationCommand extends Command
{
    protected $signature = 'make:agent-evaluation {name : The name of the agent to evaluate}';

    protected $description = 'Create a new agent evaluation';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');

        if (preg_match('/[^A-Za-z0-9_\/]/', $name) || str_contains($name, '..')) {
            $this->components->error('The name may only contain letters, numbers, underscores, and forward slashes.');

            return self::FAILURE;
        }

        $basePath = config('ai-agent-evaluation.path', base_path('agent-evaluations'));

        $evaluationFile = $basePath.'/'.$name.'Evaluation.php';

        if ($files->exists($evaluationFile)) {
            $this->components->error("Evaluation [{$name}Evaluation.php] already exists.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($basePath);

        $stub = $files->get($this->stubPath());
        $stub = str_replace('{{ agent }}', $name, $stub);

        $files->put($evaluationFile, $stub);

        $this->components->info("Evaluation [{$name}Evaluation.php] created successfully.");

        return self::SUCCESS;
    }

    private function stubPath(): string
    {
        return __DIR__.'/../../stubs/agent-evaluation.stub';
    }
}
