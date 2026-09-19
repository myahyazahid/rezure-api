<x-dashboard-layout
    title="Build & Deploy Logs"
    subtitle="Real-time pipeline tracking and execution history for: {{ $blog->title }}"
>
    <x-slot:actions>
        <a
            href="{{ route('dashboard.blogs.index') }}"
            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm font-medium text-muted hover:bg-surface-raised hover:text-foreground transition-colors"
        >
            &larr; Back to articles
        </a>

        @if ($latestRun && !empty($latestRun["html_url"]))
            <a
                href="{{ $latestRun['html_url'] }}"
                target="_blank"
                rel="noreferrer"
                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm font-medium text-muted hover:bg-surface-raised hover:text-foreground transition-colors"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
                GitHub Run #{{ $latestRun["run_number"] ?? "" }}
            </a>
        @endif

        <form method="POST" action="{{ route('dashboard.blogs.retrigger', $blog) }}" class="inline">
            @csrf
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-3.5 py-1.5 text-sm font-medium text-white hover:bg-brand/90 transition-colors"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l6.17-6.19"></path>
                </svg>
                Trigger Rebuild
            </button>
        </form>
    </x-slot:actions>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg border border-negative/30 bg-negative/10 px-4 py-2.5 text-sm text-negative">
            {{ session('error') }}
        </div>
    @endif

    {{-- Post Quick Info Card --}}
    <div class="rounded-xl border border-border bg-surface p-5 mb-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground">{{ $blog->title }}</h2>
                    @if ($blog->status === 'published')
                        <span class="inline-flex items-center gap-1 rounded-full bg-positive/15 px-2 py-0.5 text-xs font-medium text-positive">
                            <span class="h-1.5 w-1.5 rounded-full bg-positive"></span>
                            Live
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-raised px-2 py-0.5 text-xs font-medium text-subtle">
                            <span class="h-1.5 w-1.5 rounded-full bg-subtle"></span>
                            Draft
                        </span>
                    @endif
                </div>
                <div class="mt-1 flex items-center gap-2 text-xs text-subtle font-mono">
                    <span>/blog/posts/{{ $blog->slug }}</span>
                    @if ($blog->status === 'published')
                        <span>&middot;</span>
                        <a href="https://rezure.redscale.my.id/blog/posts/{{ $blog->slug }}" target="_blank" rel="noreferrer" class="text-brand hover:underline">
                            Open website
                        </a>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-6 text-xs text-muted">
                <div>
                    <span class="block text-subtle uppercase text-[10px]">Author</span>
                    <span class="font-medium text-foreground">{{ $blog->author['name'] ?? 'Team' }}</span>
                </div>
                <div>
                    <span class="block text-subtle uppercase text-[10px]">Last Updated</span>
                    <span class="font-medium text-foreground">{{ $blog->updated_at->diffForHumans() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Pipeline Visual Progress Card --}}
    <div class="rounded-xl border border-border bg-surface p-5 mb-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-subtle">Automated Pipeline Stages</p>
                <p class="text-xs text-muted mt-0.5">Static generation and VPS deployment lifecycle</p>
            </div>

            @if ($latestRun)
                @php
                    $runStatus = $latestRun['status'] ?? 'unknown';
                    $runConclusion = $latestRun['conclusion'] ?? null;
                @endphp
                <div class="flex items-center gap-2">
                    <span class="text-xs text-subtle">Status:</span>
                    @if ($runConclusion === 'success')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-positive/15 px-2.5 py-0.5 text-xs font-medium text-positive">
                            <span class="h-1.5 w-1.5 rounded-full bg-positive"></span>
                            Success
                        </span>
                    @elseif ($runConclusion === 'failure')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-negative/15 px-2.5 py-0.5 text-xs font-medium text-negative">
                            <span class="h-1.5 w-1.5 rounded-full bg-negative"></span>
                            Failed
                        </span>
                    @elseif ($runStatus === 'in_progress')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand/15 px-2.5 py-0.5 text-xs font-medium text-brand animate-pulse">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand"></span>
                            Building & Deploying...
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-raised px-2.5 py-0.5 text-xs font-medium text-subtle">
                            {{ ucfirst($runStatus) }}
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- 4-Stage Stepper --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            {{-- Stage 1: Dashboard Dispatch --}}
            <div class="rounded-lg border border-border bg-surface-raised p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-foreground">1. Dashboard Dispatch</span>
                    <span class="h-2 w-2 rounded-full bg-positive"></span>
                </div>
                <p class="mt-1 text-[11px] text-muted">Triggered by admin action</p>
                <p class="mt-2 text-[10px] text-subtle font-mono">POST /dispatches</p>
            </div>

            {{-- Stage 2: GitHub Actions --}}
            <div class="rounded-lg border border-border bg-surface-raised p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-foreground">2. GitHub Actions</span>
                    @if ($latestRun && ($latestRun['status'] ?? '') === 'in_progress')
                        <span class="h-2 w-2 rounded-full bg-brand animate-ping"></span>
                    @elseif ($latestRun && ($latestRun['conclusion'] ?? '') === 'success')
                        <span class="h-2 w-2 rounded-full bg-positive"></span>
                    @elseif ($latestRun && ($latestRun['conclusion'] ?? '') === 'failure')
                        <span class="h-2 w-2 rounded-full bg-negative"></span>
                    @else
                        <span class="h-2 w-2 rounded-full bg-subtle"></span>
                    @endif
                </div>
                <p class="mt-1 text-[11px] text-muted">Workflow runner initialized</p>
                <p class="mt-2 text-[10px] text-subtle font-mono">Build Blog</p>
            </div>

            {{-- Stage 3: Fetch & Build --}}
            <div class="rounded-lg border border-border bg-surface-raised p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-foreground">3. Fetch API & Build</span>
                    @if ($latestRun && ($latestRun['conclusion'] ?? '') === 'success')
                        <span class="h-2 w-2 rounded-full bg-positive"></span>
                    @elseif ($latestRun && ($latestRun['conclusion'] ?? '') === 'failure')
                        <span class="h-2 w-2 rounded-full bg-negative"></span>
                    @else
                        <span class="h-2 w-2 rounded-full bg-subtle"></span>
                    @endif
                </div>
                <p class="mt-1 text-[11px] text-muted">Update cache & compile HTML</p>
                <p class="mt-2 text-[10px] text-subtle font-mono">npm run docs:build</p>
            </div>

            {{-- Stage 4: VPS Deploy --}}
            <div class="rounded-lg border border-border bg-surface-raised p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-foreground">4. VPS Deploy</span>
                    @if ($latestRun && ($latestRun['conclusion'] ?? '') === 'success')
                        <span class="h-2 w-2 rounded-full bg-positive"></span>
                    @elseif ($latestRun && ($latestRun['conclusion'] ?? '') === 'failure')
                        <span class="h-2 w-2 rounded-full bg-negative"></span>
                    @else
                        <span class="h-2 w-2 rounded-full bg-subtle"></span>
                    @endif
                </div>
                <p class="mt-1 text-[11px] text-muted">SSH auto-pull on VPS</p>
                <p class="mt-2 text-[10px] text-subtle font-mono">202.10.48.147</p>
            </div>
        </div>
    </div>

    {{-- Live Steps from GitHub Actions (if available) --}}
    @if (!empty($jobs))
        <div class="rounded-xl border border-border bg-surface p-5 mb-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-subtle">GitHub Actions Execution Steps</p>
                    <p class="text-xs text-muted mt-0.5">Detailed step-by-step progress from runner</p>
                </div>
                @if ($latestRun)
                    <span class="text-xs font-mono text-subtle">Run #{{ $latestRun['run_number'] ?? '' }} (ID: {{ $latestRun['id'] ?? '' }})</span>
                @endif
            </div>

            <div class="space-y-2">
                @foreach ($jobs as $job)
                    @foreach ($job['steps'] ?? [] as $step)
                        @php
                            $stepStatus = $step['status'] ?? '';
                            $stepConclusion = $step['conclusion'] ?? '';
                        @endphp
                        <div class="flex items-center justify-between rounded-lg border border-border bg-surface-raised px-4 py-2.5 text-xs">
                            <div class="flex items-center gap-3">
                                @if ($stepConclusion === 'success')
                                    <svg class="h-4 w-4 text-positive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                @elseif ($stepConclusion === 'failure')
                                    <svg class="h-4 w-4 text-negative" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                @elseif ($stepStatus === 'in_progress')
                                    <svg class="h-4 w-4 text-brand animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                                    </svg>
                                @else
                                    <span class="h-4 w-4 rounded-full border border-subtle flex items-center justify-center text-[10px] text-subtle">&bull;</span>
                                @endif

                                <span class="font-medium text-foreground">{{ $step['name'] }}</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="text-subtle uppercase text-[10px] font-mono">{{ $stepConclusion ?: $stepStatus }}</span>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @elseif (!$hasToken)
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-5 mb-5 text-xs">
            <p class="font-semibold text-amber-500">GitHub API Token Not Configured</p>
            <p class="mt-1 text-muted">
                To fetch live step-by-step runner logs directly into this page, make sure <code class="text-foreground">GITHUB_TOKEN</code> is added to your <code class="text-foreground">.env</code> file.
                You can still view the workflow runs directly on <a href="https://github.com/{{ $repo }}/actions" target="_blank" rel="noreferrer" class="underline text-brand">GitHub Actions</a>.
            </p>
        </div>
    @endif

    {{-- Local Dispatch History Table --}}
    <div class="rounded-xl border border-border bg-surface">
        <div class="px-5 py-4 border-b border-border">
            <p class="text-xs font-medium uppercase tracking-wide text-subtle">Local Dispatch History</p>
            <p class="mt-0.5 text-xs text-muted">Every trigger sent from this dashboard to GitHub Actions</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                        <th class="px-5 py-3 font-medium">Timestamp</th>
                        <th class="px-5 py-3 font-medium">Action</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Response Code</th>
                        <th class="px-5 py-3 font-medium">Details / Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-5 py-3 text-xs text-foreground whitespace-nowrap">
                                <div>{{ $log->created_at->format('M d, Y H:i:s') }}</div>
                                <div class="text-[11px] text-subtle">{{ $log->created_at->diffForHumans() }}</div>
                            </td>

                            <td class="px-5 py-3 text-xs text-foreground font-mono whitespace-nowrap">
                                <span class="rounded bg-surface-raised px-2 py-0.5">
                                    {{ $log->action }}
                                </span>
                            </td>

                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($log->status === 'in_progress')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-brand/15 px-2 py-0.5 text-xs font-medium text-brand">
                                        <span class="h-1.5 w-1.5 rounded-full bg-brand animate-pulse"></span>
                                        Dispatched
                                    </span>
                                @elseif ($log->status === 'failed')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-negative/15 px-2 py-0.5 text-xs font-medium text-negative">
                                        <span class="h-1.5 w-1.5 rounded-full bg-negative"></span>
                                        Failed
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-positive/15 px-2 py-0.5 text-xs font-medium text-positive">
                                        <span class="h-1.5 w-1.5 rounded-full bg-positive"></span>
                                        {{ ucfirst($log->status) }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-3 text-xs font-mono text-muted whitespace-nowrap">
                                {{ $log->response_code ? $log->response_code . ' HTTP' : '&mdash;' }}
                            </td>

                            <td class="px-5 py-3 text-xs text-muted max-w-md truncate">
                                @if ($log->error_message)
                                    <span class="text-negative">{{ $log->error_message }}</span>
                                @else
                                    <span class="text-subtle">Triggered by {{ $log->user?->name ?? 'System' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-6 text-center text-xs text-subtle" colspan="5">
                                No dispatch logs recorded for this article yet. Click "Trigger Rebuild" above to send one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>