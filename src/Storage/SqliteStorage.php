<?php

namespace Casawatt\LaravelAiAgentEvaluation\Storage;

use Casawatt\LaravelAiAgentEvaluation\ResultStatus;
use PDO;

class SqliteStorage extends AbstractStorage
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $this->ensureDirectory(dirname($path), withGitignore: true);

        $this->pdo = new PDO('sqlite:'.$path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA busy_timeout=5000');

        $this->createTables();
    }

    public function createRun(): string
    {
        $id = $this->generateRunId();

        $stmt = $this->pdo->prepare('INSERT INTO runs (id, created_at) VALUES (?, ?)');
        $stmt->execute([$id, date('Y-m-d\TH:i:sP')]);

        return $id;
    }

    public function saveResult(string $runId, string $resultKey, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO results (run_id, result_key, evaluation, case_name, variant, status, data, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );

        $stmt->execute([
            $runId,
            $resultKey,
            $data['evaluation'] ?? '',
            $data['case'] ?? '',
            $data['variant'] ?? '',
            $data['status'] ?? 'passed',
            json_encode($data, JSON_UNESCAPED_SLASHES),
            date('Y-m-d\TH:i:sP'),
        ]);
    }

    public function getResults(string $runId): array
    {
        $stmt = $this->pdo->prepare('SELECT result_key, data FROM results WHERE run_id = ?');
        $stmt->execute([$runId]);

        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['result_key']] = json_decode($row['data'], true);
        }

        return $results;
    }

    public function getLatestRunId(): ?string
    {
        $stmt = $this->pdo->query('SELECT id FROM runs ORDER BY created_at DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['id'] : null;
    }

    public function getResultsExcludingStatus(string $runId, ResultStatus $excludeStatus): array
    {
        $stmt = $this->pdo->prepare('SELECT result_key, data FROM results WHERE run_id = ? AND status != ?');
        $stmt->execute([$runId, $excludeStatus->value]);

        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['result_key']] = json_decode($row['data'], true);
        }

        return $results;
    }

    public function clear(): void
    {
        $this->pdo->exec('DELETE FROM results');
        $this->pdo->exec('DELETE FROM runs');
        $this->pdo->exec('VACUUM');
    }

    public function prune(int $days = 30): void
    {
        $cutoff = date('Y-m-d\TH:i:sP', time() - $days * 86400);

        $stmt = $this->pdo->prepare('DELETE FROM results WHERE run_id IN (SELECT id FROM runs WHERE created_at < ?)');
        $stmt->execute([$cutoff]);

        $stmt = $this->pdo->prepare('DELETE FROM runs WHERE created_at < ?');
        $stmt->execute([$cutoff]);

        $this->pdo->exec('VACUUM');
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS runs (
                id TEXT PRIMARY KEY,
                created_at TEXT NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS results (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id TEXT NOT NULL,
                result_key TEXT NOT NULL,
                evaluation TEXT NOT NULL,
                case_name TEXT NOT NULL,
                variant TEXT NOT NULL,
                status TEXT NOT NULL,
                data TEXT NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE(run_id, result_key)
            )
        ');

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_results_run_id ON results(run_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_results_status ON results(run_id, status)');
    }
}
