<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClassSwitcher from '@/components/halden/ClassSwitcher.vue';

interface Measure {
    key: string;
    name: string;
    unit: string;
    here: number | null;
    all: number | null;
    hereBest: number | null;
    best: number | null;
}

const props = defineProps<{
    section: { id: number; name: string; course: string };
    classes: { id: number; name: string; course: string }[];
    rows: {
        quarter: string;
        number: number;
        teams: number;
        otherTeams: number;
        otherClasses: number;
        measures: Measure[];
    }[];
}>();

const q = `?section=${props.section.id}`;

function fmt(v: number | null, unit: string): string {
    if (v === null) {
        return '';
    }
    switch (unit) {
        case 'usd2':
            return `$${v.toFixed(2)}`;
        case 'pct':
            return `${v.toFixed(1)}%`;
        case 'musd':
            return `$${Math.round(v).toLocaleString('en-US')}M`;
        case 'kusd':
            return `$${v.toFixed(1)}k`;
        case 'x':
            return `${v.toFixed(2)}x`;
        default:
            return v.toFixed(1);
    }
}
</script>

<template>
    <Head :title="`${section.name} · Other classes`" />
    <div class="hx min-h-screen">
        <header
            class="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5 md:px-7"
            style="background: var(--hx-night); color: #e6ecef"
        >
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <span
                    class="hx-mono text-[13px] tracking-[0.2em]"
                    style="color: var(--hx-mint)"
                    >HALDEN · FACULTY</span
                >
                <ClassSwitcher
                    :classes="classes"
                    :current="section.id"
                    path="/faculty/benchmarks"
                />
                <span class="hx-hint" style="color: #b8c4c9"
                    >Against other classes</span
                >
            </div>
            <div class="flex gap-4 text-[13px]">
                <a :href="`/faculty${q}`" style="color: var(--hx-mint)"
                    >Faculty board</a
                >
                <a href="/settings/profile" style="color: var(--hx-mint)"
                    >Settings</a
                >
            </div>
        </header>

        <main class="mx-auto max-w-[1000px] px-4 py-7 md:px-5">
            <h1 class="hx-h1">How this class compares</h1>
            <p class="hx-hint mt-1 max-w-[760px]">
                For every quarter with results shown, the middle team and the
                best team in this class against the middle and the best across
                every class that has run the same quarter, on the seven score
                measures and EBITDA. Other classes are counted, not named. The
                comparison is rough: each class draws its own OPEC+ outcome,
                breakdown and world, so the same decisions can land differently.
            </p>

            <p v-if="rows.length === 0" class="hx-hint mt-6">
                Nothing to compare yet. Results appear here once a quarter's
                results are shown.
            </p>

            <section v-for="r in rows" :key="r.number" class="hx-card mt-6 p-0">
                <div class="px-4 pt-4">
                    <h2 class="hx-h2">Q{{ r.number }} · {{ r.quarter }}</h2>
                    <p class="hx-hint">
                        {{ r.teams }} teams here;
                        <template v-if="r.otherClasses > 0"
                            >{{ r.otherTeams }} more in
                            {{ r.otherClasses }} other
                            {{
                                r.otherClasses === 1 ? 'class' : 'classes'
                            }}.</template
                        >
                        <template v-else
                            >no other class has run this quarter yet.</template
                        >
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="hx-tbl mt-2 w-full">
                        <thead>
                            <tr>
                                <th scope="col">Measure</th>
                                <th scope="col">This class, middle</th>
                                <th scope="col">All classes, middle</th>
                                <th scope="col">This class, best</th>
                                <th scope="col">All classes, best</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in r.measures" :key="m.key">
                                <th scope="row" class="font-normal">
                                    {{ m.name }}
                                </th>
                                <td class="hx-mono">
                                    {{ fmt(m.here, m.unit) }}
                                </td>
                                <td class="hx-mono">
                                    {{ fmt(m.all, m.unit) }}
                                </td>
                                <td class="hx-mono">
                                    {{ fmt(m.hereBest, m.unit) }}
                                </td>
                                <td class="hx-mono">
                                    {{ fmt(m.best, m.unit) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</template>
