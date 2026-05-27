<?php

namespace Casawatt\LaravelAiAgentEvaluation\Http\Controllers;

use Casawatt\LaravelAiAgentEvaluation\Reporter\HtmlReportPresenter;
use Casawatt\LaravelAiAgentEvaluation\Storage\StorageInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    public function index(StorageInterface $storage): View
    {
        return view('ai-agent-evaluation::index', [
            'runs' => $storage->listRuns(),
        ]);
    }

    public function show(string $runId, StorageInterface $storage, HtmlReportPresenter $presenter): View
    {
        $rows = $storage->getResults($runId);

        abort_if($rows === [], 404);

        return view('ai-agent-evaluation::report', [
            'runId' => $runId,
            'evaluations' => $presenter->present($rows),
        ]);
    }

    public function destroy(string $runId, StorageInterface $storage): RedirectResponse
    {
        $storage->deleteRun($runId);

        return redirect()->route('ai-agent-evaluation.index');
    }
}
