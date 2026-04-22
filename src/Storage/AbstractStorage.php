<?php

namespace Casawatt\LaravelAiAgentEvaluation\Storage;

abstract class AbstractStorage implements StorageInterface
{
    protected function ensureDirectory(string $dir, bool $withGitignore = false): void
    {
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0o755, true) && ! is_dir($dir)) {
                throw new \RuntimeException("Cannot create storage directory: {$dir}");
            }

            if ($withGitignore) {
                file_put_contents($dir.'/.gitignore', "*\n!.gitignore\n");
            }
        }
    }

    protected function generateRunId(): string
    {
        return date('Y-m-d\THis').'-'.bin2hex(random_bytes(4));
    }
}
