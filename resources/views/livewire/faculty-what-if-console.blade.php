<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <section class="rounded-lg border p-5">
        <p class="text-muted-foreground text-sm">Faculty tools</p>
        <h1 class="mt-1 text-2xl font-semibold">What-if console</h1>
    </section>

    <section class="rounded-lg border p-5">
        <div class="grid gap-3 md:grid-cols-4">
            <label class="space-y-1 text-sm">
                <span class="font-medium">Section</span>
                <select class="w-full rounded-md border bg-background px-3 py-2" wire:model.live="sectionSimulationId">
                    @foreach ($sectionSimulations as $sectionSimulation)
                        <option value="{{ $sectionSimulation->id }}">{{ $sectionSimulation->section->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1 text-sm">
                <span class="font-medium">Team</span>
                <select class="w-full rounded-md border bg-background px-3 py-2" wire:model.live="teamSimulationId">
                    @foreach ($teamSimulations as $teamSimulation)
                        <option value="{{ $teamSimulation->id }}">{{ $teamSimulation->team->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1 text-sm">
                <span class="font-medium">Source result</span>
                <select class="w-full rounded-md border bg-background px-3 py-2" wire:model.live="sourceResolutionId">
                    @foreach ($sourceResolutions as $resolution)
                        <option value="{{ $resolution->id }}">
                            Week {{ $resolution->runtimeWeek->definition->week_number }} - {{ $resolution->transfer_price }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1 text-sm">
                <span class="font-medium">Transfer price</span>
                <input class="w-full rounded-md border bg-background px-3 py-2" type="text" wire:model="transferPrice">
            </label>
        </div>

        <button
            type="button"
            class="mt-4 rounded-md border px-3 py-2 text-sm hover:bg-accent"
            wire:click="runScenario"
            @disabled($sourceResolutionId === null)
        >
            Run what-if
        </button>
    </section>

    <section class="rounded-lg border p-5">
        @if ($latestRun === null)
            <p class="text-muted-foreground text-sm">Run a counterfactual transfer-price scenario to view results.</p>
        @else
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-muted-foreground text-sm">Counterfactual result</p>
                    <h2 class="text-lg font-medium">{{ $latestRun->scenario_type }}</h2>
                </div>
                <span class="text-muted-foreground text-sm">Run #{{ $latestRun->id }}</span>
            </div>

            <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground text-xs">Counterfactual</dt>
                    <dd>{{ $latestRun->counterfactual ? 'true' : 'false' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Transfer price</dt>
                    <dd>{{ $latestRun->scenario_inputs['counterfactual_transfer_price'] ?? '' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Integrated margin</dt>
                    <dd>{{ $latestRun->calculated_outputs['segment_result']['integrated_margin'] ?? '' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Geneva capture per bbl</dt>
                    <dd>{{ $latestRun->calculated_outputs['geneva_arbitrage']['capture_per_bbl'] ?? '' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Upstream margin delta</dt>
                    <dd>{{ $latestRun->calculated_outputs['deltas']['upstream_margin'] ?? '' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Refining margin delta</dt>
                    <dd>{{ $latestRun->calculated_outputs['deltas']['refining_margin'] ?? '' }}</dd>
                </div>
            </dl>
        @endif
    </section>
</div>
