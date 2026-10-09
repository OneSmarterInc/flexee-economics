<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface TeamRow {
    id: number;
    name: string;
    members: number;
    pages: { page: string; title: string; changed: boolean }[];
    memoWords: number;
    ready: boolean;
    lastActivity: string | null;
    score: number | null;
    rank: number | null;
    flag: string | null;
}

const props = defineProps<{
    section: { id: number; name: string; course: string };
    quarter: {
        id: number;
        number: number;
        label: string;
        status: 'upcoming' | 'open' | 'closed' | 'published';
        deadline: string | null;
        deadlineText: string | null;
    } | null;
    next: { id: number; label: string; buildable: boolean } | null;
    pages: { page: string; title: string }[];
    teams: TeamRow[];
    quarters: { number: number; label: string; status: string }[];
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const busy = ref(false);

const readyCount = computed(() => props.teams.filter((t) => t.ready).length);

const statusText: Record<string, string> = {
    upcoming: 'Not open yet',
    open: 'Open for decisions',
    closed: 'Closed, results not shown to students yet',
    published: 'Results shown to students',
};

const confirmText: Record<string, string> = {
    open: 'Open this quarter for decisions? Students will see the briefing right away.',
    close: 'Close the quarter now? Whatever each team has saved is what runs.',
    publish: "Show results to students? This can't be taken back.",
    extend: 'Push the deadline back by one day?',
};

function act(
    quarterId: number,
    action: 'open' | 'close' | 'publish' | 'extend',
) {
    if (!window.confirm(confirmText[action])) {
        return;
    }

    busy.value = true;
    router.post(
        `/faculty/quarters/${quarterId}/${action}?section=${props.section.id}`,
        {},
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}

function ago(iso: string | null): string {
    if (!iso) {
        return 'No activity yet';
    }

    const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

    if (mins < 1) {
        return 'Just now';
    }

    if (mins < 60) {
        return `${mins} min ago`;
    }

    const hours = Math.round(mins / 60);

    if (hours < 48) {
        return `${hours} hr ago`;
    }

    return `${Math.round(hours / 24)} days ago`;
}

function teamHref(teamId: number): string {
    return props.quarter
        ? `/faculty/teams/${teamId}/quarters/${props.quarter.id}?section=${props.section.id}`
        : '#';
}
</script>

<template>
    <Head :title="`${section.name} · Faculty`" />
    <div class="hx min-h-screen">
        <header
            class="flex flex-wrap items-center justify-between gap-3 px-7 py-3.5"
            style="background: var(--hx-night); color: #e6ecef"
        >
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <span
                    class="hx-mono text-[13px] tracking-[0.2em]"
                    style="color: var(--hx-mint)"
                    >HALDEN · FACULTY</span
                >
                <span class="font-semibold">{{ section.name }}</span>
                <span style="color: #c9d4da">{{ section.course }}</span>
            </div>
            <a
                href="/settings/profile"
                class="text-[13px]"
                style="color: var(--hx-mint)"
                >Settings</a
            >
        </header>

        <main class="mx-auto max-w-[1100px] px-5 py-7">
            <div v-if="quarter === null" class="hx-card">
                This class has no quarters yet.
            </div>

            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="hx-eyebrow">This week</p>
                        <h1 class="hx-h1 mt-1">
                            Quarter {{ quarter.number }} · {{ quarter.label }}
                        </h1>
                        <p class="mt-1">{{ statusText[quarter.status] }}</p>
                        <p v-if="quarter.deadlineText" class="hx-hint">
                            Deadline: {{ quarter.deadlineText }} (Eastern)
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template v-if="quarter.status === 'open'">
                            <button
                                type="button"
                                class="hx-btn hx-btn-outline"
                                :disabled="busy"
                                @click="act(quarter.id, 'extend')"
                            >
                                Extend one day
                            </button>
                            <button
                                type="button"
                                class="hx-btn hx-btn-amber"
                                :disabled="busy"
                                @click="act(quarter.id, 'close')"
                            >
                                Close and run the quarter
                            </button>
                        </template>
                        <button
                            v-else-if="quarter.status === 'closed'"
                            type="button"
                            class="hx-btn hx-btn-primary"
                            :disabled="busy"
                            @click="act(quarter.id, 'publish')"
                        >
                            Show results to students
                        </button>
                        <button
                            v-else-if="quarter.status === 'upcoming'"
                            type="button"
                            class="hx-btn hx-btn-primary"
                            :disabled="busy"
                            @click="act(quarter.id, 'open')"
                        >
                            Open Quarter {{ quarter.number }}
                        </button>
                        <template v-else-if="next">
                            <button
                                v-if="next.buildable"
                                type="button"
                                class="hx-btn hx-btn-primary"
                                :disabled="busy"
                                @click="act(next.id, 'open')"
                            >
                                Open {{ next.label }}
                            </button>
                            <p v-else class="hx-hint max-w-[320px]">
                                {{ next.label }} isn't built yet. It will be
                                ready in a later release.
                            </p>
                        </template>
                    </div>
                </div>

                <p v-for="(e, k) in errors" :key="k" class="hx-error mt-3">
                    {{ e }}
                </p>

                <div class="hx-card mt-6 overflow-x-auto p-0">
                    <table class="hx-tbl w-full">
                        <caption class="hx-sr">
                            Where each team stands this quarter
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Team</th>
                                <th
                                    v-for="p in pages"
                                    :key="p.page"
                                    scope="col"
                                >
                                    {{ p.title }}
                                </th>
                                <th scope="col">Memo</th>
                                <th scope="col">Ready</th>
                                <th scope="col">Last activity</th>
                                <th
                                    v-if="
                                        quarter.status !== 'open' &&
                                        quarter.status !== 'upcoming'
                                    "
                                    scope="col"
                                >
                                    Score
                                </th>
                                <th scope="col">
                                    <span class="hx-sr">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in teams" :key="t.id">
                                <th scope="row" class="font-semibold">
                                    {{ t.name }}
                                    <span class="hx-hint block font-normal"
                                        >{{ t.members }} students</span
                                    >
                                </th>
                                <td v-for="p in t.pages" :key="p.page">
                                    <span
                                        v-if="p.changed"
                                        style="color: var(--hx-teal)"
                                        >Saved</span
                                    >
                                    <span v-else class="hx-hint"
                                        >Same as last quarter</span
                                    >
                                </td>
                                <td>
                                    <span v-if="t.memoWords > 0"
                                        >{{ t.memoWords }} words</span
                                    >
                                    <span v-else class="hx-hint">{{
                                        t.flag
                                    }}</span>
                                </td>
                                <td>
                                    <span
                                        v-if="t.ready"
                                        style="color: var(--hx-teal)"
                                        >Yes</span
                                    >
                                    <span v-else class="hx-hint">Not yet</span>
                                </td>
                                <td class="hx-hint">
                                    {{ ago(t.lastActivity) }}
                                </td>
                                <td
                                    v-if="
                                        quarter.status !== 'open' &&
                                        quarter.status !== 'upcoming'
                                    "
                                >
                                    <template v-if="t.score !== null">
                                        {{ t.score.toFixed(1) }}
                                        <span class="hx-hint"
                                            >(#{{ t.rank }})</span
                                        >
                                    </template>
                                </td>
                                <td>
                                    <a :href="teamHref(t.id)"
                                        >See their screens</a
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="hx-hint mt-2">
                    {{ readyCount }} of {{ teams.length }} teams have marked
                    themselves ready.
                </p>

                <h2 class="hx-h2 mt-8">All quarters</h2>
                <ol class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <li
                        v-for="q in quarters"
                        :key="q.number"
                        class="rounded-md border px-3 py-2"
                        :style="
                            q.number === quarter.number
                                ? 'border-color: var(--hx-teal); background: var(--hx-teal-wash)'
                                : 'border-color: var(--hx-line)'
                        "
                    >
                        <span class="font-semibold">Q{{ q.number }}</span> ·
                        {{ q.label }}
                        <span class="hx-hint block">{{
                            statusText[q.status] ?? q.status
                        }}</span>
                    </li>
                </ol>
            </template>
        </main>
    </div>
</template>
