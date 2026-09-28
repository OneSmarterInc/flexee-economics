<section class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-muted-foreground">Faculty operations</p>
            <h1 class="text-3xl font-semibold">Simulation control dashboard</h1>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            <a class="rounded-md border px-3 py-2 hover:bg-muted" href="{{ route('faculty.week-control') }}">Open week control</a>
            <a class="rounded-md border px-3 py-2 hover:bg-muted" href="{{ route('faculty.causal-trace') }}">Open causal trace</a>
            <a class="rounded-md border px-3 py-2 hover:bg-muted" href="{{ route('faculty.what-if') }}">Open what-if console</a>
        </div>
    </div>

    @if ($sectionSimulations->isEmpty())
        <div class="rounded-lg border p-6">
            <h2 class="text-lg font-semibold">No assigned simulations</h2>
            <p class="mt-2 text-sm text-muted-foreground">No authorized section simulations are available for this account.</p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
            <div class="space-y-6">
                <div class="rounded-lg border p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Course / section</h2>
                            <p class="text-sm text-muted-foreground">Choose the classroom simulation to inspect.</p>
                        </div>
                        <label class="flex flex-col gap-1 text-sm">
                            <span class="font-medium">Section simulation</span>
                            <select class="min-w-72 rounded-md border bg-background px-3 py-2" wire:model.live="sectionSimulationId">
                                @foreach ($sectionSimulations as $sectionSimulation)
                                    <option value="{{ $sectionSimulation->id }}">{{ $sectionSimulation->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    @if ($sectionSummary)
                        <dl class="mt-5 grid gap-3 md:grid-cols-3">
                            <div class="rounded-md bg-muted p-3">
                                <dt class="text-xs uppercase text-muted-foreground">Course</dt>
                                <dd class="font-medium">{{ $sectionSummary['course'] }}</dd>
                            </div>
                            <div class="rounded-md bg-muted p-3">
                                <dt class="text-xs uppercase text-muted-foreground">Section</dt>
                                <dd class="font-medium">{{ $sectionSummary['section'] }}</dd>
                            </div>
                            <div class="rounded-md bg-muted p-3">
                                <dt class="text-xs uppercase text-muted-foreground">Teams / students</dt>
                                <dd class="font-medium">{{ $sectionSummary['teams'] }} teams / {{ $sectionSummary['students'] }} students</dd>
                            </div>
                        </dl>
                    @endif
                </div>

                <div class="rounded-lg border p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Week timeline</h2>
                            <p class="text-sm text-muted-foreground">Completed, active, blocked, and pending weeks for this section.</p>
                        </div>
                        @if ($selectedWeek)
                            <span class="rounded-md bg-muted px-3 py-1 text-sm">Selected: Week {{ $selectedWeek->definition->week_number }}</span>
                        @endif
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($timeline as $week)
                            <button
                                type="button"
                                wire:click="$set('runtimeWeekId', {{ $week['id'] }})"
                                class="rounded-lg border p-4 text-left hover:bg-muted {{ $runtimeWeekId === $week['id'] ? 'ring-2 ring-primary' : '' }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold">Week {{ $week['number'] }}</p>
                                        <p class="mt-1 text-sm text-muted-foreground">{{ $week['title'] }}</p>
                                    </div>
                                    <span class="rounded-md bg-muted px-2 py-1 text-xs font-medium uppercase">{{ $week['state'] }}</span>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                                    <div>
                                        <dt>Status</dt>
                                        <dd class="font-medium text-foreground">{{ $week['status'] }}</dd>
                                    </div>
                                    <div>
                                        <dt>Execution</dt>
                                        <dd class="font-medium text-foreground">{{ $week['execution_status'] }}</dd>
                                    </div>
                                </dl>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border p-5">
                    <h2 class="text-lg font-semibold">Results review</h2>
                    <p class="text-sm text-muted-foreground">Economic results, KPI basis, ranking state, and consequence availability.</p>

                    @if ($results)
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div class="rounded-md bg-muted p-4">
                                <h3 class="font-medium">{{ $results['economic']['label'] }}</h3>
                                <p class="mt-2 text-2xl font-semibold">{{ $results['economic']['count'] }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ $results['economic']['note'] }}</p>
                                @if (count($results['economic']['status_counts']) > 0)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($results['economic']['status_counts'] as $status => $count)
                                            <span class="rounded-md border bg-background px-2 py-1 text-xs">{{ $status }}: {{ $count }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="rounded-md bg-muted p-4">
                                <h3 class="font-medium">KPI Basis</h3>
                                <p class="mt-2 text-2xl font-semibold">{{ $results['kpi']['available'] }} available / {{ $results['kpi']['unavailable'] }} unavailable</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ $results['kpi']['note'] }}</p>
                            </div>

                            <div class="rounded-md bg-muted p-4">
                                <h3 class="font-medium">Ranking</h3>
                                <p class="mt-2 text-2xl font-semibold">{{ $results['ranking']['complete'] }} complete / {{ $results['ranking']['incomplete'] }} incomplete</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ $results['ranking']['note'] }}</p>
                            </div>

                            <div class="rounded-md bg-muted p-4">
                                <h3 class="font-medium">Consequences</h3>
                                <p class="mt-2 text-2xl font-semibold">{{ $results['consequence_count'] }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ $results['consequence_note'] }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-lg border p-5">
                    <h2 class="text-lg font-semibold">Current week</h2>
                    @if ($currentWeekSummary)
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Week</dt>
                                <dd class="font-medium">Week {{ $currentWeekSummary['number'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Title</dt>
                                <dd class="text-right font-medium">{{ $currentWeekSummary['title'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Lifecycle</dt>
                                <dd class="font-medium">{{ $currentWeekSummary['status'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Content</dt>
                                <dd class="font-medium">{{ $currentWeekSummary['content_status'] }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Execution</dt>
                                <dd class="font-medium">{{ $currentWeekSummary['execution_status'] }}</dd>
                            </div>
                        </dl>
                    @endif
                </div>

                <div class="rounded-lg border p-5">
                    <h2 class="text-lg font-semibold">Readiness</h2>
                    @if ($readiness)
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-md bg-muted p-3">
                                <dt class="text-muted-foreground">Teams</dt>
                                <dd class="text-xl font-semibold">{{ $readiness['teams'] }}</dd>
                            </div>
                            <div class="rounded-md bg-muted p-3">
                                <dt class="text-muted-foreground">Complete</dt>
                                <dd class="text-xl font-semibold">{{ $readiness['complete'] }}</dd>
                            </div>
                            <div class="rounded-md bg-muted p-3">
                                <dt class="text-muted-foreground">Ready</dt>
                                <dd class="text-xl font-semibold">{{ $readiness['ready'] }}</dd>
                            </div>
                            <div class="rounded-md bg-muted p-3">
                                <dt class="text-muted-foreground">Pending</dt>
                                <dd class="text-xl font-semibold">{{ $readiness['pending'] }}</dd>
                            </div>
                        </dl>
                    @endif
                </div>

                <div class="rounded-lg border p-5">
                    <h2 class="text-lg font-semibold">Operations</h2>
                    @if ($operations)
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Package</dt>
                                <dd class="font-medium">{{ $operations['content']['status'] }} / {{ $operations['content']['detail'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Artifacts visible to faculty</dt>
                                <dd class="font-medium">{{ $operations['content']['artifact_count'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Execution state</dt>
                                <dd class="font-medium">{{ $operations['execution_status'] }}</dd>
                            </div>
                        </dl>

                        @if (count($operations['execution_steps']) > 0)
                            <div class="mt-4 space-y-2">
                                @foreach ($operations['execution_steps'] as $step)
                                    <div class="rounded-md border bg-background p-3 text-sm">
                                        <div class="flex justify-between gap-3">
                                            <span class="font-medium">{{ str_replace('_', ' ', $step['key']) }}</span>
                                            <span class="uppercase text-muted-foreground">{{ $step['status'] }}</span>
                                        </div>
                                        <p class="mt-1 text-muted-foreground">{{ $step['summary'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>

                <div class="rounded-lg border p-5">
                    <h2 class="text-lg font-semibold">Analysis tools</h2>
                    <div class="mt-4 grid gap-2 text-sm">
                        <a class="rounded-md border px-3 py-2 hover:bg-muted" href="{{ route('faculty.causal-trace') }}">Trace decisions and outcomes</a>
                        <a class="rounded-md border px-3 py-2 hover:bg-muted" href="{{ route('faculty.what-if') }}">Run supported what-if analysis</a>
                        <a class="rounded-md border px-3 py-2 hover:bg-muted" href="{{ route('faculty.week-control') }}">Execute or inspect week control</a>
                    </div>
                </div>
            </aside>
        </div>
    @endif
</section>
