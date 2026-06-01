<!doctype html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Agent Evaluation — {{ $runId }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full text-slate-800 antialiased">

@php
    /**
     * @var string $runId
     * @var array<int, array{name:string,cases:array<int,string>,variants:array<int,string>,matrix:array<string,array<string,array<string,mixed>>>,totals:array<string,array<string,mixed>>}> $evaluations
     */

    $statusClasses = [
        'passed'  => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
        'failed'  => 'bg-rose-100 text-rose-700 ring-1 ring-rose-200',
        'error'   => 'bg-red-200 text-red-800 ring-1 ring-red-300',
        'skipped' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
    ];

    $statusLabels = [
        'passed'  => 'PASS',
        'failed'  => 'FAIL',
        'error'   => 'ERROR',
        'skipped' => 'SKIP',
    ];

    $formatLatency = static function (?float $seconds): string {
        if ($seconds === null) {
            return '—';
        }
        return $seconds < 1
            ? number_format($seconds * 1000).' ms'
            : number_format($seconds, 2).' s';
    };

    $formatTokens = static fn (?float $t) => $t === null ? '—' : number_format($t).' tok';

    $formatCost = static function (?float $cost): string {
        if ($cost === null) {
            return '—';
        }
        if ($cost < 0.01) {
            return '$'.number_format($cost, 5);
        }
        return '$'.number_format($cost, 4);
    };

    $totalResults = array_sum(array_map(static fn ($e) => count($e['cases']) * count($e['variants']), $evaluations));
@endphp

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto px-4 py-5 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('ai-agent-evaluation.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800">← All runs</a>
                <h1 class="mt-1 text-lg font-semibold tracking-tight text-slate-900">AI Agent Evaluation Report</h1>
                <p class="mt-0.5 text-sm text-slate-500">
                    Run <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-700">{{ $runId }}</code>
                    · {{ count($evaluations) }} {{ \Illuminate\Support\Str::plural('evaluation', count($evaluations)) }}
                </p>
            </div>
        </div>
    </div>
</header>

<main class="mx-auto space-y-10 px-4 py-8 sm:px-6 lg:px-8">

    @foreach ($evaluations as $eval)
        @php $variantCount = count($eval['variants']); @endphp

        <section x-data="{ open: null }" class="space-y-4">

            <div class="flex items-baseline justify-between">
                <h2 class="text-base font-semibold text-slate-900">{{ $eval['name'] }}</h2>
                <p class="text-xs text-slate-500">
                    {{ count($eval['cases']) }} {{ \Illuminate\Support\Str::plural('case', count($eval['cases'])) }}
                    × {{ $variantCount }} {{ \Illuminate\Support\Str::plural('variant', $variantCount) }}
                </p>
            </div>

            {{-- Per-variant summary --}}
            <div class="grid gap-3"
                 style="grid-template-columns: repeat({{ max($variantCount, 1) }}, minmax(0, 1fr));">
                @foreach ($eval['variants'] as $variant)
                    @php
                        $t = $eval['totals'][$variant];
                        $totalCount = $t['passed'] + $t['failed'] + $t['errored'] + $t['skipped'];
                        $passRate = $totalCount > 0 ? ($t['passed'] / $totalCount) * 100 : 0;
                    @endphp
                    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                        <p class="truncate font-mono text-xs text-slate-500" title="{{ $variant }}">{{ $variant }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($passRate) }}<span class="text-base text-slate-400">%</span></p>
                        <div class="mt-2 flex flex-wrap gap-1 text-[10px] uppercase tracking-wide">
                            @if ($t['passed']  > 0) <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-emerald-700">{{ $t['passed'] }} pass</span> @endif
                            @if ($t['failed']  > 0) <span class="rounded bg-rose-100    px-1.5 py-0.5 text-rose-700">{{ $t['failed'] }} fail</span> @endif
                            @if ($t['errored'] > 0) <span class="rounded bg-red-200     px-1.5 py-0.5 text-red-800">{{ $t['errored'] }} err</span> @endif
                            @if ($t['skipped'] > 0) <span class="rounded bg-amber-100   px-1.5 py-0.5 text-amber-700">{{ $t['skipped'] }} skip</span> @endif
                        </div>
                        <dl class="mt-3 space-y-0.5 text-xs text-slate-600">
                            <div class="flex justify-between"><dt>Avg latency</dt><dd class="font-mono">{{ $formatLatency($t['avg_latency']) }}</dd></div>
                            <div class="flex justify-between"><dt>Avg tokens</dt><dd class="font-mono">{{ $formatTokens($t['avg_tokens']) }}</dd></div>
                            <div class="flex justify-between"><dt>Avg cost</dt><dd class="font-mono">{{ $formatCost($t['avg_cost']) }}</dd></div>
                            @if ($t['avg_score_percent'] !== null)
                                <div class="flex justify-between"><dt>Avg score</dt><dd class="font-mono">{{ number_format($t['avg_score_percent']) }}%</dd></div>
                            @endif
                        </dl>

                        @if (! empty($t['metrics']))
                            <div class="mt-3 border-t border-slate-100 pt-2">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Metrics</p>
                                <ul class="space-y-1">
                                    @foreach ($t['metrics'] as $metric => $pct)
                                        @php
                                            $metricColor = match (true) {
                                                $pct >= 75 => 'bg-emerald-500',
                                                $pct >= 50 => 'bg-amber-500',
                                                default    => 'bg-rose-500',
                                            };
                                            $metricText = match (true) {
                                                $pct >= 75 => 'text-emerald-700',
                                                $pct >= 50 => 'text-amber-700',
                                                default    => 'text-rose-700',
                                            };
                                        @endphp
                                        <li>
                                            <div class="flex items-center justify-between gap-2 text-[11px]">
                                                <span class="truncate text-slate-600" title="{{ $metric }}">{{ $metric }}</span>
                                                <span class="font-mono {{ $metricText }}">{{ number_format($pct) }}%</span>
                                            </div>
                                            <div class="mt-0.5 h-1 w-full overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full {{ $metricColor }}" style="width: {{ number_format($pct, 1) }}%"></div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Matrix --}}
            <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="sticky left-0 z-10 bg-slate-50 px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Case
                            </th>
                            @foreach ($eval['variants'] as $variant)
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <span class="font-mono normal-case">{{ $variant }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($eval['cases'] as $case)
                            <tr class="align-top">
                                <td class="sticky left-0 z-10 bg-white px-4 py-3 font-mono text-xs text-slate-700">
                                    {{ $case }}
                                </td>

                                @foreach ($eval['variants'] as $variant)
                                    @php
                                        $cell = $eval['matrix'][$case][$variant] ?? null;
                                        $cellKey = $case.'::'.$variant;
                                    @endphp
                                    <td class="px-3 py-3 align-top">
                                        @if ($cell === null)
                                            <span class="text-slate-300">—</span>
                                        @else
                                            @php
                                                $status = (string) ($cell['status'] ?? 'failed');
                                                $statusClass = $statusClasses[$status] ?? $statusClasses['failed'];
                                                $statusLabel = $statusLabels[$status] ?? strtoupper($status);
                                                $preview = (string) ($cell['response_text'] ?? $cell['failure_message'] ?? $cell['skip_reason'] ?? '');
                                                $previewShort = mb_strlen($preview) > 200 ? mb_substr($preview, 0, 200).'…' : $preview;
                                                $tokens = ($cell['usage']['prompt_tokens'] ?? 0) + ($cell['usage']['completion_tokens'] ?? 0);
                                                $score = $cell['score'] ?? null;
                                                $scorePct = (is_array($score) && ($score['total_weight'] ?? 0) > 0)
                                                    ? ($score['passed_weight'] / $score['total_weight']) * 100
                                                    : null;
                                                $scoreClass = $scorePct === null ? '' : match (true) {
                                                    $scorePct >= 75 => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
                                                    $scorePct >= 50 => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
                                                    default => 'bg-rose-100 text-rose-700 ring-1 ring-rose-200',
                                                };
                                            @endphp

                                            <button type="button"
                                                    @click="open = open === @js($cellKey) ? null : @js($cellKey)"
                                                    class="group block w-full max-w-md rounded-md border border-slate-200 bg-white p-2 text-left transition hover:border-slate-300 hover:shadow-sm">
                                                <div class="flex items-center justify-between gap-2">
                                                    <div class="flex items-center gap-1">
                                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold tracking-wide {{ $statusClass }}">
                                                            {{ $statusLabel }}
                                                        </span>
                                                        @if ($scorePct !== null)
                                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold tracking-wide {{ $scoreClass }}">
                                                                {{ number_format($scorePct) }}%
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <span class="font-mono text-[10px] text-slate-400">
                                                        {{ $formatLatency($cell['latency_seconds'] ?? null) }}
                                                        · {{ $formatTokens((int) $tokens) }}
                                                    </span>
                                                </div>
                                                @if ($previewShort !== '')
                                                    <p class="mt-2 whitespace-pre-wrap text-xs leading-snug text-slate-600 line-clamp-4">{{ $previewShort }}</p>
                                                @endif
                                            </button>

                                            <div x-cloak
                                                 x-show="open === @js($cellKey)"
                                                 x-transition.opacity
                                                 class="mt-2 max-w-md space-y-3 rounded-md border border-slate-200 bg-slate-50 p-3 text-xs">

                                                @if (! empty($cell['prompt_text']))
                                                    <div>
                                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Prompt</p>
                                                        <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words rounded bg-white p-2 font-mono text-[11px] text-slate-700">{{ $cell['prompt_text'] }}</pre>
                                                    </div>
                                                @endif

                                                @if (! empty($cell['response_text']))
                                                    <div>
                                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Response</p>
                                                        <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words rounded bg-white p-2 font-mono text-[11px] text-slate-700">{{ $cell['response_text'] }}</pre>
                                                    </div>
                                                @endif

                                                @if (! empty($cell['failure_message']))
                                                    <div>
                                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-rose-600">Failure</p>
                                                        <pre class="whitespace-pre-wrap break-words rounded bg-rose-50 p-2 font-mono text-[11px] text-rose-800">{{ $cell['failure_message'] }}</pre>
                                                    </div>
                                                @endif

                                                @if (! empty($cell['skip_reason']))
                                                    <div>
                                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-amber-600">Skipped</p>
                                                        <p class="text-amber-800">{{ $cell['skip_reason'] }}</p>
                                                    </div>
                                                @endif

                                                @if (is_array($score) && ! empty($score['assertions']))
                                                    <div>
                                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Assertions</p>
                                                        <ul class="space-y-1">
                                                            @foreach ($score['assertions'] as $assertion)
                                                                <li class="flex items-start gap-2 rounded bg-white p-1.5">
                                                                    <span class="mt-0.5 inline-flex h-4 w-4 flex-none items-center justify-center rounded-full {{ ($assertion['passed'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }} text-[10px] font-bold">
                                                                        {{ ($assertion['passed'] ?? false) ? '✓' : '✗' }}
                                                                    </span>
                                                                    <div class="flex-1">
                                                                        <p class="font-mono text-[11px] text-slate-700">
                                                                            {{ $assertion['assertion'] ?? 'assertion' }}
                                                                            @if (! empty($assertion['weight']))
                                                                                <span class="text-slate-400">(w={{ $assertion['weight'] }})</span>
                                                                            @endif
                                                                        </p>
                                                                        @if (! empty($assertion['message']))
                                                                            <p class="mt-0.5 text-[11px] text-slate-500">{{ $assertion['message'] }}</p>
                                                                        @endif
                                                                    </div>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif

                                                <dl class="grid grid-cols-2 gap-x-3 gap-y-1 border-t border-slate-200 pt-2 text-[11px] text-slate-600">
                                                    <div><dt class="inline text-slate-400">Provider</dt> <dd class="inline font-mono">{{ $cell['provider'] ?? '—' }}</dd></div>
                                                    <div><dt class="inline text-slate-400">Model</dt> <dd class="inline font-mono">{{ $cell['model'] ?? '—' }}</dd></div>
                                                    <div><dt class="inline text-slate-400">Latency</dt> <dd class="inline font-mono">{{ $formatLatency($cell['latency_seconds'] ?? null) }}</dd></div>
                                                    <div><dt class="inline text-slate-400">Tokens</dt> <dd class="inline font-mono">{{ $formatTokens((int) $tokens) }}</dd></div>
                                                    <div><dt class="inline text-slate-400">Cost</dt> <dd class="inline font-mono">{{ $formatCost($cell['cost'] ?? null) }}</dd></div>
                                                    @if (($cell['temperature'] ?? null) !== null)
                                                        <div><dt class="inline text-slate-400">Temp</dt> <dd class="inline font-mono">{{ $cell['temperature'] }}</dd></div>
                                                    @endif
                                                    @if (($cell['top_p'] ?? null) !== null)
                                                        <div><dt class="inline text-slate-400">Top P</dt> <dd class="inline font-mono">{{ $cell['top_p'] }}</dd></div>
                                                    @endif
                                                    @if (($cell['max_tokens'] ?? null) !== null)
                                                        <div><dt class="inline text-slate-400">Max tokens</dt> <dd class="inline font-mono">{{ $cell['max_tokens'] }}</dd></div>
                                                    @endif
                                                    @if (($cell['max_steps'] ?? null) !== null)
                                                        <div><dt class="inline text-slate-400">Max steps</dt> <dd class="inline font-mono">{{ $cell['max_steps'] }}</dd></div>
                                                    @endif
                                                    @if ($scorePct !== null)
                                                        <div><dt class="inline text-slate-400">Score</dt> <dd class="inline font-mono">{{ number_format($scorePct) }}%</dd></div>
                                                    @endif
                                                </dl>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </section>
    @endforeach

    @if ($evaluations === [])
        <p class="text-sm text-slate-500">No results for this run.</p>
    @endif

</main>

</body>
</html>
