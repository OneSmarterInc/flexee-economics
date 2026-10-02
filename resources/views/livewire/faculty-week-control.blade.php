<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <section class="rounded-lg border p-5">
        <p class="text-muted-foreground text-sm">Faculty tools</p>
        <h1 class="mt-1 text-2xl font-semibold">Week control</h1>
    </section>

    <section class="rounded-lg border p-5">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium">Section simulation</span>
                <select wire:model.live="sectionSimulationId" class="bg-background mt-2 w-full rounded-md border px-3 py-2 text-sm">
                    @foreach ($sectionSimulations as $sectionSimulation)
                        <option value="{{ $sectionSimulation->id }}">
                            {{ $sectionSimulation->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium">Runtime week</span>
                <select wire:model.live="runtimeWeekId" class="bg-background mt-2 w-full rounded-md border px-3 py-2 text-sm">
                    @foreach ($runtimeWeeks as $runtimeWeek)
                        <option value="{{ $runtimeWeek->id }}">
                            Week {{ $runtimeWeek->definition->week_number }}: {{ $runtimeWeek->definition->title }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    @if ($selectedSectionSimulation && $selectedRuntimeWeek)
        <section class="rounded-lg border p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="font-medium">
                        Week {{ $selectedRuntimeWeek->definition->week_number }}: {{ $selectedRuntimeWeek->definition->title }}
                    </h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ $selectedSectionSimulation->section->course->name }} -
                        {{ $selectedSectionSimulation->section->name }} -
                        {{ $selectedSectionSimulation->version->version }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($validTransitions as $nextStatus)
                        <button
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm hover:bg-accent"
                            wire:click="transitionSelectedWeek('{{ $nextStatus->value }}')"
                        >
                            {{ ucfirst($nextStatus->value) }}
                        </button>
                    @endforeach

                    <button
                        type="button"
                        class="bg-primary text-primary-foreground rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                        wire:click="executeSelectedWeek"
                        @disabled($executionRecord !== null)
                    >
                        Execute week
                    </button>
                </div>
            </div>

            @error('execution')
                <p class="mt-3 rounded-md border border-destructive/40 p-3 text-sm text-destructive">{{ $message }}</p>
            @enderror
        </section>

        <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-lg border p-4">
                <p class="text-muted-foreground text-sm">Week state</p>
                <p class="mt-1 text-lg font-semibold">{{ $selectedRuntimeWeek->statusValue() }}</p>
            </article>

            <article class="rounded-lg border p-4">
                <p class="text-muted-foreground text-sm">Content package</p>
                <p class="mt-1 text-lg font-semibold">{{ $contentState['status'] ?? 'unavailable' }}</p>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{ $contentState['package_version'] ?? ($contentState['message'] ?? 'No package') }}
                </p>
            </article>

            <article class="rounded-lg border p-4">
                <p class="text-muted-foreground text-sm">Submission state</p>
                <p class="mt-1 text-lg font-semibold">
                    {{ $submissionState['complete_count'] ?? 0 }} / {{ $submissionState['team_count'] ?? 0 }} complete
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    Decisions {{ $submissionState['decision_submitted_count'] ?? 0 }},
                    allocations {{ $submissionState['capital_allocation_submitted_count'] ?? 0 }},
                    memos {{ $submissionState['memo_submitted_count'] ?? 0 }}
                </p>
            </article>

            <article class="rounded-lg border p-4">
                <p class="text-muted-foreground text-sm">Execution state</p>
                <p class="mt-1 text-lg font-semibold">{{ $executionRecord?->status ?? 'not_run' }}</p>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{ $executionRecord?->execution_version ?? 'week_execution_v1' }}
                </p>
            </article>
        </section>

        <section class="rounded-lg border p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-medium">Week execution</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ $executionRecord?->failure_message ?? 'Execution record steps' }}
                    </p>
                </div>
                <p class="text-muted-foreground text-sm">{{ $executionRecord?->started_at?->toDayDateTimeString() }}</p>
            </div>

            @if ($executionRecord)
                <div class="mt-4 divide-y rounded-md border">
                    @foreach ($executionSteps as $step)
                        <div class="grid gap-2 p-3 text-sm md:grid-cols-[220px_120px_1fr]">
                            <p class="font-medium">{{ str_replace('_', ' ', $step['key'] ?? 'unknown') }}</p>
                            <p>{{ $step['status'] ?? 'unknown' }}</p>
                            <p class="text-muted-foreground">{{ $step['summary'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-muted-foreground mt-4 text-sm">No execution record exists for this runtime week.</p>
            @endif
        </section>
    @else
        <section class="rounded-lg border p-5">
            <p class="text-muted-foreground text-sm">No section simulations are assigned yet.</p>
        </section>
    @endif
</div>
