<?php

namespace Casawatt\LaravelAiAgentEvaluation\Tests\Http;

use Casawatt\LaravelAiAgentEvaluation\Storage\FileStorage;
use Casawatt\LaravelAiAgentEvaluation\Storage\StorageInterface;
use Casawatt\LaravelAiAgentEvaluation\Tests\TestCase;

class HttpTestCase extends TestCase
{
    protected string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir().'/ai-agent-evaluation-test-'.uniqid();

        parent::setUp();

        $this->app->singleton(StorageInterface::class, fn () => new FileStorage($this->storagePath));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->deleteDirectory($this->storagePath);
    }

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.debug', true);
        $app['config']->set('app.env', 'local');
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function seedRun(string $runId, array $rows): void
    {
        /** @var FileStorage $storage */
        $storage = $this->app->make(StorageInterface::class);

        $runDir = $this->storagePath.'/'.$runId;
        if (! is_dir($runDir)) {
            mkdir($runDir, 0o755, true);
        }

        foreach ($rows as $row) {
            $resultKey = sprintf(
                '%s::%s::%s',
                $row['evaluation'] ?? 'Evaluation',
                $row['case'] ?? 'case',
                $row['variant'] ?? 'variant',
            );

            $storage->saveResult($runId, $resultKey, $row);
        }
    }
}
