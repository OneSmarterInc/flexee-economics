<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <section class="rounded-lg border p-5">
        <p class="text-muted-foreground text-sm">Halden Energy</p>
        <h1 class="mt-1 text-2xl font-semibold">Simulation lifecycle</h1>
    </section>

    <div class="space-y-4">
        @forelse ($sectionSimulations as $sectionSimulation)
            <section class="rounded-lg border p-5">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="font-medium">{{ $sectionSimulation->name }}</h2>
                        <p class="text-muted-foreground text-sm">
                            {{ $sectionSimulation->section->course->name }} - {{ $sectionSimulation->section->name }} -
                            {{ $sectionSimulation->variant->name }} {{ $sectionSimulation->version->version }}
                        </p>
                    </div>
                    <span class="text-muted-foreground text-sm">{{ $sectionSimulation->status->value }}</span>
                </div>

                <div class="mt-4 divide-y rounded-md border">
                    @foreach ($sectionSimulation->weeks->sortBy(fn ($runtimeWeek) => $runtimeWeek->definition->week_number) as $runtimeWeek)
                        <div class="flex flex-col gap-3 p-3 lg:flex-row lg:items-center lg:justify-between" wire:key="week-{{ $runtimeWeek->id }}">
                            <div>
                                <p class="font-medium">
                                    Week {{ $runtimeWeek->definition->week_number }}: {{ $runtimeWeek->definition->title }}
                                </p>
                                <p class="text-muted-foreground text-sm">{{ $runtimeWeek->status->value }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach (($validTransitions[$runtimeWeek->status->value] ?? []) as $nextStatus)
                                    <button
                                        type="button"
                                        class="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                                        wire:click="transitionWeek({{ $runtimeWeek->id }}, '{{ $nextStatus->value }}')"
                                    >
                                        {{ ucfirst($nextStatus->value) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="rounded-lg border p-5">
                <p class="text-muted-foreground text-sm">No section simulations are assigned yet.</p>
            </section>
        @endforelse
    </div>
</div>
