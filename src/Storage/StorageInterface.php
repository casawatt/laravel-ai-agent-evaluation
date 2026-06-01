<?php

namespace Casawatt\LaravelAiAgentEvaluation\Storage;

use Casawatt\LaravelAiAgentEvaluation\ResultStatus;

interface StorageInterface
{
    /**
     * Create a new evaluation run and return its ID.
     */
    public function createRun(): string;

    /**
     * Save a single result for a run.
     */
    public function saveResult(string $runId, string $resultKey, array $data): void;

    /**
     * Get all results for a run, keyed by result_key.
     *
     * @return array<string, array>
     */
    public function getResults(string $runId): array;

    /**
     * Get the most recent run ID.
     */
    public function getLatestRunId(): ?string;

    /**
     * List all runs, newest first, with status counts.
     *
     * @return array<int, array{
     *     id: string,
     *     created_at: string,
     *     result_count: int,
     *     passed: int,
     *     failed: int,
     *     errored: int,
     *     skipped: int
     * }>
     */
    public function listRuns(): array;

    /**
     * Get all result keys for a run, optionally excluding a given status.
     *
     * @return array<string, array> Keyed by result_key.
     */
    public function getResultsExcludingStatus(string $runId, ResultStatus $excludeStatus): array;

    /**
     * Delete a single run and all its results.
     */
    public function deleteRun(string $runId): void;

    /**
     * Delete all runs and results.
     */
    public function clear(): void;

    /**
     * Delete runs older than the given number of days.
     */
    public function prune(int $days = 30): void;
}
