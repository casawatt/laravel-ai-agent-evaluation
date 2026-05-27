<!doctype html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Agent Evaluation — Runs</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full text-slate-800 antialiased">

@php
    /** @var array<int, array{id:string,created_at:string,result_count:int,passed:int,failed:int,errored:int,skipped:int}> $runs */

    $formatCreatedAt = static function (string $createdAt): string {
        $ts = strtotime($createdAt);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : $createdAt;
    };
@endphp

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto px-4 py-5 sm:px-6 lg:px-8">
        <h1 class="text-lg font-semibold tracking-tight text-slate-900">AI Agent Evaluation Runs</h1>
        <p class="mt-0.5 text-sm text-slate-500">
            {{ count($runs) }} {{ \Illuminate\Support\Str::plural('run', count($runs)) }} stored
        </p>
    </div>
</header>

<main class="mx-auto px-4 py-8 sm:px-6 lg:px-8">

    @if ($runs === [])
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-sm font-medium text-slate-700">No runs yet.</p>
            <p class="mt-1 text-xs text-slate-500">
                Run <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">php artisan agent-evaluation</code>
                to produce a report.
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Run</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Results</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Pass rate</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Breakdown</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($runs as $run)
                        @php
                            $passRate = $run['result_count'] > 0 ? ($run['passed'] / $run['result_count']) * 100 : 0;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('ai-agent-evaluation.show', $run['id']) }}"
                                   class="block font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                    {{ $run['id'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                {{ $formatCreatedAt($run['created_at']) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-slate-700">
                                {{ $run['result_count'] }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($run['result_count'] > 0)
                                    <span class="font-mono text-xs text-slate-700">{{ number_format($passRate) }}%</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 text-[10px] uppercase tracking-wide">
                                    @if ($run['passed']  > 0) <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-emerald-700">{{ $run['passed'] }} pass</span> @endif
                                    @if ($run['failed']  > 0) <span class="rounded bg-rose-100    px-1.5 py-0.5 text-rose-700">{{ $run['failed'] }} fail</span> @endif
                                    @if ($run['errored'] > 0) <span class="rounded bg-red-200     px-1.5 py-0.5 text-red-800">{{ $run['errored'] }} err</span> @endif
                                    @if ($run['skipped'] > 0) <span class="rounded bg-amber-100   px-1.5 py-0.5 text-amber-700">{{ $run['skipped'] }} skip</span> @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form action="{{ route('ai-agent-evaluation.destroy', $run['id']) }}" method="POST"
                                      onsubmit="return confirm('Delete run {{ $run['id'] }} and all its results?');">
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-medium text-rose-600 hover:text-rose-800 hover:underline">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</main>

</body>
</html>
