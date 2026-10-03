<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type Artifact = {
    key: string;
    label: string;
    type: string;
    visibility?: string | null;
    version?: string | null;
    reference: string;
};

type CurrentContent = {
    package: {
        status: string;
        package_type?: string;
        version?: string | null;
        validation_status?: string | null;
        message?: string | null;
    };
    artifacts: Artifact[];
};

type WeekSummary = {
    number: number;
    title: string;
    status: string;
    timeline_status: string;
    url?: string | null;
    can_write: boolean;
    decision_status: string;
    memo_status: string;
    capital_allocation_status?: string | null;
    complete: boolean;
    ready_for_evaluation: boolean;
    resolution_status: string;
    resolved_at?: string | null;
};

type TimelineWeek = {
    number: number;
    title: string;
    state: string;
    status: string;
    url?: string | null;
};

type HistoryWeek = {
    number: number;
    title: string;
    url: string;
    decision_status: string;
    decision_submitted_at?: string | null;
    memo_status: string;
    memo_submitted_at?: string | null;
    resolution_status: string;
    resolved_at?: string | null;
    result_summary?: {
        label: string;
        status: string;
        detail: string;
        available_kpis: number;
    } | null;
};

type SimulationJourney = {
    course: string;
    section: string;
    team: string;
    simulation: string;
    variant: string;
    version: string;
    variant_summary: {
        duration_weeks: number;
        sequence: number[];
        is_seven_week_variant: boolean;
    };
    role_rotation?: {
        phase: string;
        label: string;
        phase_weeks: number[];
        description: string;
        seat_name?: string | null;
        rotation_note: string;
    } | null;
    current_week: WeekSummary | null;
    current_content: CurrentContent | null;
    progress: {
        completed: number;
        visible: number;
        total: number;
    };
    timeline: TimelineWeek[];
    history: HistoryWeek[];
};

defineProps<{
    journey: {
        tenant?: {
            name: string;
            slug: string;
        } | null;
        simulations: SimulationJourney[];
    };
}>();

function statusLabel(status?: string | null) {
    return status ? status.replaceAll('_', ' ') : 'not started';
}
</script>

<template>
    <Head title="Student journey" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <section class="rounded-lg border p-5">
            <p class="text-muted-foreground text-sm">
                {{ journey.tenant?.name ?? 'Halden simulation' }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold">
                Student simulation journey
            </h1>
            <p class="text-muted-foreground mt-2 max-w-3xl text-sm">
                Track your current week, authorized materials, submitted work,
                and your own team history.
            </p>
        </section>

        <section
            v-if="!journey.simulations.length"
            class="rounded-lg border p-5"
        >
            <h2 class="font-medium">No active simulation</h2>
            <p class="text-muted-foreground mt-2 text-sm">
                You do not have an assigned team simulation yet.
            </p>
        </section>

        <template
            v-for="simulation in journey.simulations"
            :key="`${simulation.course}-${simulation.section}-${simulation.team}`"
        >
            <section
                class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]"
            >
                <div class="rounded-lg border p-5">
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                    >
                        <div>
                            <p class="text-muted-foreground text-sm">
                                {{ simulation.course }} /
                                {{ simulation.section }}
                            </p>
                            <h2 class="mt-1 text-xl font-semibold">
                                {{ simulation.simulation }}
                            </h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{ simulation.team }} / {{ simulation.variant }}
                                {{ simulation.version }}
                            </p>
                            <p
                                v-if="
                                    simulation.variant_summary
                                        .is_seven_week_variant
                                "
                                class="text-muted-foreground mt-2 text-sm"
                            >
                                Pilot sequence:
                                {{
                                    simulation.variant_summary.sequence
                                        .map((week) => `Week ${week}`)
                                        .join(' → ')
                                }}
                            </p>
                        </div>
                        <div
                            class="grid gap-2 text-sm sm:grid-cols-3 md:min-w-96"
                        >
                            <div class="rounded-md border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Completed
                                </p>
                                <p class="text-lg font-semibold">
                                    {{ simulation.progress.completed }}
                                </p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Visible
                                </p>
                                <p class="text-lg font-semibold">
                                    {{ simulation.progress.visible }}
                                </p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Total
                                </p>
                                <p class="text-lg font-semibold">
                                    {{ simulation.progress.total }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border p-5">
                    <h2 class="font-medium">Current week</h2>
                    <template v-if="simulation.current_week">
                        <p class="mt-3 text-lg font-semibold">
                            Week {{ simulation.current_week.number }}:
                            {{ simulation.current_week.title }}
                        </p>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                            <div class="rounded-md border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Week
                                </p>
                                <p class="font-medium">
                                    {{
                                        statusLabel(
                                            simulation.current_week.status,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Result
                                </p>
                                <p class="font-medium">
                                    {{
                                        statusLabel(
                                            simulation.current_week
                                                .resolution_status,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Decision
                                </p>
                                <p class="font-medium">
                                    {{
                                        statusLabel(
                                            simulation.current_week
                                                .decision_status,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Memo
                                </p>
                                <p class="font-medium">
                                    {{
                                        statusLabel(
                                            simulation.current_week.memo_status,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <Link
                            v-if="simulation.current_week.url"
                            class="bg-primary text-primary-foreground mt-4 inline-flex rounded-md px-3 py-2 text-sm"
                            :href="simulation.current_week.url"
                        >
                            Open current week
                        </Link>
                    </template>
                    <p v-else class="text-muted-foreground mt-3 text-sm">
                        No week is currently available.
                    </p>
                </div>
            </section>

            <section
                v-if="simulation.role_rotation"
                class="rounded-lg border p-5"
            >
                <div
                    class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                >
                    <div>
                        <p class="text-muted-foreground text-sm">
                            Seven-week role rotation
                        </p>
                        <h2 class="mt-1 font-medium">
                            {{ simulation.role_rotation.label }}
                        </h2>
                        <p class="text-muted-foreground mt-2 text-sm">
                            {{ simulation.role_rotation.description }}
                        </p>
                    </div>
                    <div class="rounded-md border p-3 text-sm md:min-w-60">
                        <p class="text-muted-foreground text-xs">
                            Current assigned seat
                        </p>
                        <p class="mt-1 font-medium">
                            {{
                                simulation.role_rotation.seat_name ??
                                'Assigned team role'
                            }}
                        </p>
                        <p class="text-muted-foreground mt-2 text-xs">
                            Covers
                            {{
                                simulation.role_rotation.phase_weeks
                                    .map((week) => `Week ${week}`)
                                    .join(', ')
                            }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border p-5">
                <h2 class="font-medium">Week timeline</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <component
                        :is="week.url ? Link : 'div'"
                        v-for="week in simulation.timeline"
                        :key="week.number"
                        :href="week.url ?? undefined"
                        class="rounded-md border p-3 text-sm"
                        :class="{
                            'bg-muted': week.state === 'active',
                            'opacity-70': !week.url,
                        }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    Week {{ week.number }}
                                </p>
                                <p class="text-muted-foreground mt-1">
                                    {{ week.title }}
                                </p>
                            </div>
                            <span
                                class="rounded-md border px-2 py-1 text-xs uppercase"
                            >
                                {{ week.state }}
                            </span>
                        </div>
                    </component>
                </div>
                <p
                    v-if="simulation.variant_summary.is_seven_week_variant"
                    class="text-muted-foreground mt-4 text-sm"
                >
                    The pilot ends with Week 14 Board Defense, where your team
                    defends the full decision history rather than running a new
                    economic engine.
                </p>
            </section>

            <section
                class="grid gap-4 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]"
            >
                <div class="rounded-lg border p-5">
                    <h2 class="font-medium">Materials for current week</h2>
                    <template v-if="simulation.current_content">
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{
                                simulation.current_content.package.status ===
                                'active'
                                    ? `Active ${simulation.current_content.package.version ?? ''}`.trim()
                                    : simulation.current_content.package.message
                            }}
                        </p>

                        <div
                            v-if="simulation.current_content.artifacts.length"
                            class="mt-4 space-y-3"
                        >
                            <article
                                v-for="artifact in simulation.current_content
                                    .artifacts"
                                :key="artifact.key"
                                class="rounded-md border p-3 text-sm"
                            >
                                <p class="font-medium">{{ artifact.label }}</p>
                                <p class="text-muted-foreground mt-1">
                                    {{ artifact.type }}
                                </p>
                                <p
                                    class="text-muted-foreground mt-2 text-xs break-all"
                                >
                                    {{ artifact.reference }}
                                </p>
                            </article>
                        </div>
                        <p v-else class="text-muted-foreground mt-4 text-sm">
                            No student materials are available yet.
                        </p>
                    </template>
                </div>

                <div class="rounded-lg border p-5">
                    <h2 class="font-medium">Your history</h2>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="week in simulation.history"
                            :key="week.number"
                            class="rounded-md border p-4"
                        >
                            <div
                                class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                            >
                                <div>
                                    <p class="font-medium">
                                        Week {{ week.number }}: {{ week.title }}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-sm"
                                    >
                                        Decision
                                        {{ statusLabel(week.decision_status) }}
                                        / Memo
                                        {{ statusLabel(week.memo_status) }}
                                    </p>
                                </div>
                                <Link
                                    class="rounded-md border px-3 py-2 text-sm"
                                    :href="week.url"
                                >
                                    Open
                                </Link>
                            </div>

                            <div
                                v-if="week.result_summary"
                                class="bg-muted mt-3 rounded-md p-3 text-sm"
                            >
                                <p class="font-medium">
                                    {{ week.result_summary.label }}
                                </p>
                                <p class="text-muted-foreground mt-1">
                                    {{ week.result_summary.detail }}
                                </p>
                                <p class="text-muted-foreground mt-1">
                                    Available KPI snapshots:
                                    {{ week.result_summary.available_kpis }}
                                </p>
                            </div>
                            <p
                                v-else
                                class="text-muted-foreground mt-3 text-sm"
                            >
                                Result pending.
                            </p>
                        </article>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
