<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <section class="rounded-lg border p-5">
        <p class="text-muted-foreground text-sm">Faculty tools</p>
        <h1 class="mt-1 text-2xl font-semibold">Causal trace</h1>
    </section>

    <section class="rounded-lg border p-5">
        <div class="grid gap-3 md:grid-cols-4">
            <label class="space-y-1 text-sm">
                <span class="font-medium">Section</span>
                <select class="w-full rounded-md border bg-background px-3 py-2" wire:model.live="sectionSimulationId">
                    @foreach ($sectionSimulations as $sectionSimulation)
                        <option value="{{ $sectionSimulation->id }}">
                            {{ $sectionSimulation->section->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1 text-sm">
                <span class="font-medium">Team</span>
                <select class="w-full rounded-md border bg-background px-3 py-2" wire:model.live="teamSimulationId">
                    @foreach ($teamSimulations as $teamSimulation)
                        <option value="{{ $teamSimulation->id }}">
                            {{ $teamSimulation->team->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1 text-sm">
                <span class="font-medium">Week</span>
                <select class="w-full rounded-md border bg-background px-3 py-2" wire:model.live="runtimeWeekId">
                    @foreach ($runtimeWeeks->sortBy(fn ($runtimeWeek) => $runtimeWeek->definition->week_number) as $runtimeWeek)
                        <option value="{{ $runtimeWeek->id }}">
                            Week {{ $runtimeWeek->definition->week_number }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1 text-sm">
                <span class="font-medium">Source</span>
                <select class="w-full rounded-md border bg-background px-3 py-2" wire:model.live="sourceType">
                    <option value="decision">Decision</option>
                    <option value="consequence">Consequence</option>
                </select>
            </label>
        </div>
    </section>

    <section class="rounded-lg border p-5">
        @if ($trace === null)
            <p class="text-muted-foreground text-sm">No trace source exists for the selected filters.</p>
        @else
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-muted-foreground text-sm">{{ ucfirst($trace->direction) }} trace</p>
                    <h2 class="text-lg font-medium">{{ ucfirst($trace->rootType) }} #{{ $trace->rootId }}</h2>
                </div>
                <span class="text-muted-foreground text-sm">{{ count($trace->nodes) }} nodes</span>
            </div>

            <div class="mt-5 space-y-3">
                @foreach ($trace->nodes as $node)
                    <article class="rounded-md border bg-background p-4" wire:key="trace-node-{{ $node->type }}-{{ $node->id }}">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-muted-foreground text-xs uppercase tracking-wide">{{ $node->type }}</p>
                                <h3 class="font-medium">{{ $node->label }}</h3>
                            </div>
                            @if ($node->runtimeWeekId)
                                <span class="text-muted-foreground text-xs">Runtime week #{{ $node->runtimeWeekId }}</span>
                            @endif
                        </div>

                        @if ($node->payload !== [])
                            <dl class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                                @foreach ($node->payload as $key => $value)
                                    @continue($value === null || $value === [])
                                    <div>
                                        <dt class="text-muted-foreground text-xs">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                                        <dd class="break-words">
                                            @if (is_array($value))
                                                {{ json_encode($value) }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
