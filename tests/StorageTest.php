<?php

use Casawatt\LaravelAiAgentEvaluation\ResultStatus;
use Casawatt\LaravelAiAgentEvaluation\Storage\FileStorage;
use Casawatt\LaravelAiAgentEvaluation\Storage\SqliteStorage;
use Casawatt\LaravelAiAgentEvaluation\Storage\StorageInterface;

/**
 * @return array<string, array{StorageInterface}>
 */
function storageBackends(): array
{
    $fileBase = sys_get_temp_dir().'/ai-agent-evaluation-list-file-'.uniqid();
    $sqliteBase = sys_get_temp_dir().'/ai-agent-evaluation-list-sqlite-'.uniqid();

    return [
        'file' => [new FileStorage($fileBase)],
        'sqlite' => [new SqliteStorage($sqliteBase.'/db.sqlite')],
    ];
}

test('listRuns returns an empty array when there are no runs', function (StorageInterface $storage) {
    expect($storage->listRuns())->toBe([]);
})->with(storageBackends());

test('listRuns returns runs with their status counts', function (StorageInterface $storage) {
    $runId = $storage->createRun();

    $statuses = ['passed', 'passed', 'failed', 'error', 'skipped'];

    foreach ($statuses as $i => $status) {
        $storage->saveResult($runId, $runId.'::case'.$i.'::v', [
            'evaluation' => 'E',
            'case' => 'case'.$i,
            'variant' => 'v',
            'status' => $status,
        ]);
    }

    $runs = $storage->listRuns();

    expect($runs)->toHaveCount(1)
        ->and($runs[0]['id'])->toBe($runId)
        ->and($runs[0]['result_count'])->toBe(5)
        ->and($runs[0]['passed'])->toBe(2)
        ->and($runs[0]['failed'])->toBe(1)
        ->and($runs[0]['errored'])->toBe(1)
        ->and($runs[0]['skipped'])->toBe(1);
})->with(storageBackends());

test('getResultsExcludingStatus drops only the excluded status', function (StorageInterface $storage) {
    $runId = $storage->createRun();

    $statuses = ['passed', 'failed', 'error', 'skipped'];

    foreach ($statuses as $status) {
        $storage->saveResult($runId, "E::{$status}::v", [
            'evaluation' => 'E',
            'case' => $status,
            'variant' => 'v',
            'status' => $status,
        ]);
    }

    // Mirrors how --retry-failed selects results: keep everything but the failures.
    $kept = $storage->getResultsExcludingStatus($runId, ResultStatus::Failed);

    expect($kept)->toHaveKeys(['E::passed::v', 'E::error::v', 'E::skipped::v'])
        ->and($kept)->not->toHaveKey('E::failed::v');
})->with(storageBackends());

test('deleteRun removes only the targeted run and its results', function (StorageInterface $storage) {
    $kept = $storage->createRun();
    $storage->saveResult($kept, $kept.'::a::v', [
        'evaluation' => 'E', 'case' => 'a', 'variant' => 'v', 'status' => 'passed',
    ]);

    $deleted = $storage->createRun();
    $storage->saveResult($deleted, $deleted.'::b::v', [
        'evaluation' => 'E', 'case' => 'b', 'variant' => 'v', 'status' => 'failed',
    ]);

    $storage->deleteRun($deleted);

    expect($storage->getResults($deleted))->toBe([])
        ->and($storage->getResults($kept))->toHaveKey($kept.'::a::v')
        ->and(array_column($storage->listRuns(), 'id'))->toBe([$kept]);
})->with(storageBackends());

test('deleteRun is a no-op for an unknown run', function (StorageInterface $storage) {
    $runId = $storage->createRun();
    $storage->saveResult($runId, $runId.'::a::v', [
        'evaluation' => 'E', 'case' => 'a', 'variant' => 'v', 'status' => 'passed',
    ]);

    $storage->deleteRun('2099-01-01T000000-deadbeef');

    expect(array_column($storage->listRuns(), 'id'))->toBe([$runId]);
})->with(storageBackends());

test('FileStorage listRuns sorts runs newest first', function () {
    $base = sys_get_temp_dir().'/ai-agent-evaluation-order-'.uniqid();
    $storage = new FileStorage($base);

    $older = '2026-01-01T100000-aaaaaaaa';
    $newer = '2026-05-13T143052-bbbbbbbb';

    // Seed in non-chronological order to prove sorting isn't insertion-order.
    foreach ([$newer, $older] as $runId) {
        $storage->saveResult($runId, $runId.'::a::v', [
            'evaluation' => 'E', 'case' => 'a', 'variant' => 'v', 'status' => 'passed',
        ]);
    }

    $runs = $storage->listRuns();

    expect($runs)->toHaveCount(2)
        ->and($runs[0]['id'])->toBe($newer)
        ->and($runs[1]['id'])->toBe($older);
});
