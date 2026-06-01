<?php

namespace Casawatt\LaravelAiAgentEvaluation\Storage;

use Casawatt\LaravelAiAgentEvaluation\ResultStatus;

class FileStorage extends AbstractStorage
{
    public function __construct(
        private readonly string $basePath,
    ) {
        $this->ensureDirectory($this->basePath, withGitignore: true);
    }

    public function createRun(): string
    {
        $id = $this->generateRunId();

        $this->ensureDirectory($this->basePath.'/'.$id);

        return $id;
    }

    public function saveResult(string $runId, string $resultKey, array $data): void
    {
        $dir = $this->basePath.'/'.$runId;

        $this->ensureDirectory($dir);

        $data['result_key'] = $resultKey;

        $filename = md5($resultKey).'.json';
        file_put_contents(
            $dir.'/'.$filename,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }

    public function getResults(string $runId): array
    {
        $dir = $this->basePath.'/'.$runId;

        if (! is_dir($dir)) {
            return [];
        }

        $results = [];

        foreach (glob($dir.'/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);

            if (is_array($data) && isset($data['result_key'])) {
                $results[$data['result_key']] = $data;
            }
        }

        return $results;
    }

    public function getLatestRunId(): ?string
    {
        $dirs = glob($this->basePath.'/20*', GLOB_ONLYDIR);

        if ($dirs === false || $dirs === []) {
            return null;
        }

        sort($dirs);

        return basename(end($dirs));
    }

    public function listRuns(): array
    {
        $dirs = glob($this->basePath.'/20*', GLOB_ONLYDIR);

        if ($dirs === false || $dirs === []) {
            return [];
        }

        rsort($dirs);

        $runs = [];

        foreach ($dirs as $dir) {
            $runId = basename($dir);
            $counts = ['passed' => 0, 'failed' => 0, 'errored' => 0, 'skipped' => 0];
            $resultCount = 0;

            foreach ((array) glob($dir.'/*.json') as $file) {
                $data = json_decode((string) file_get_contents($file), true);

                if (! is_array($data)) {
                    continue;
                }

                $resultCount++;

                match ($data['status'] ?? null) {
                    'passed' => $counts['passed']++,
                    'failed' => $counts['failed']++,
                    'error' => $counts['errored']++,
                    'skipped' => $counts['skipped']++,
                    default => null,
                };
            }

            $runs[] = [
                'id' => $runId,
                'created_at' => $this->parseCreatedAt($runId),
                'result_count' => $resultCount,
                'passed' => $counts['passed'],
                'failed' => $counts['failed'],
                'errored' => $counts['errored'],
                'skipped' => $counts['skipped'],
            ];
        }

        return $runs;
    }

    private function parseCreatedAt(string $runId): string
    {
        $timestamp = strtotime(substr($runId, 0, 17));

        return $timestamp !== false ? date('Y-m-d\TH:i:sP', $timestamp) : $runId;
    }

    public function getResultsExcludingStatus(string $runId, ResultStatus $excludeStatus): array
    {
        $all = $this->getResults($runId);

        return array_filter(
            $all,
            fn (array $result) => ($result['status'] ?? '') !== $excludeStatus->value,
        );
    }

    public function deleteRun(string $runId): void
    {
        $dir = $this->basePath.'/'.$runId;

        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }

    public function clear(): void
    {
        $dirs = glob($this->basePath.'/20*', GLOB_ONLYDIR);

        if ($dirs === false) {
            return;
        }

        foreach ($dirs as $dir) {
            $this->deleteDirectory($dir);
        }
    }

    public function prune(int $days = 30): void
    {
        $cutoff = time() - $days * 86400;
        $dirs = glob($this->basePath.'/20*', GLOB_ONLYDIR);

        if ($dirs === false) {
            return;
        }

        foreach ($dirs as $dir) {
            $runId = basename($dir);
            $timestamp = strtotime(substr($runId, 0, 17));

            if ($timestamp !== false && $timestamp < $cutoff) {
                $this->deleteDirectory($dir);
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        $entries = glob($dir.'/*');

        if ($entries !== false) {
            foreach ($entries as $entry) {
                is_dir($entry) ? $this->deleteDirectory($entry) : unlink($entry);
            }
        }

        rmdir($dir);
    }
}
