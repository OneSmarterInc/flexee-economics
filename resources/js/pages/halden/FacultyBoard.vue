<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ClassSwitcher from '@/components/halden/ClassSwitcher.vue';

interface TeamRow {
    id: number;
    name: string;
    members: number;
    pages: { page: string; title: string; changed: boolean }[];
    memoWords: number;
    ready: boolean;
    advisorAnswers: number;
    feedback: 'none' | 'drafted' | 'published';
    defense: boolean;
    verdict: 'none' | 'decided' | 'published';
    lastActivity: string | null;
    score: number | null;
    rank: number | null;
    flag: string | null;
}

interface Draw {
    key: 'opec' | 'outage' | 'world';
    title: string;
    when: string;
    value: string | null;
    settable: boolean;
    options: { value: string; label: string; chance: number }[];
    quarterId: number;
    quarterLabel: string;
    quarterNumber: number;
    open: boolean;
}

const props = defineProps<{
    section: { id: number; name: string; course: string };
    classes: { id: number; name: string; course: string }[];
    isAdmin: boolean;
    quarter: {
        id: number;
        number: number;
        label: string;
        status: 'upcoming' | 'open' | 'closed' | 'published';
        deadline: string | null;
        deadlineText: string | null;
        isBoard: boolean;
        boardQuarterId: number | null;
        boardLabel: string | null;
        world: string | null;
        week: number;
        weeks: number;
        paired: boolean;
        coverage: string | null;
    } | null;
    next: { id: number; label: string; buildable: boolean } | null;
    feedbackQuarter: { id: number; label: string } | null;
    pages: { page: string; title: string }[];
    teams: TeamRow[];
    quarters: { number: number; label: string; status: string }[];
    draws: Draw[];
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

const drawPicks = ref<Record<string, string>>(
    Object.fromEntries(
        props.draws.map((d) => [`${d.quarterId}:${d.key}`, d.value ?? '']),
    ),
);

function drawLabel(d: Draw, value: string | null): string {
    return d.options.find((o) => o.value === value)?.label ?? 'Chance';
}

function drawStatus(d: Draw): string {
    if (!d.settable) {
        return `Settled: ${drawLabel(d, d.value)}.`;
    }

    if (d.value === null) {
        return `Left to chance, drawn ${d.when}.`;
    }

    return `Set to: ${drawLabel(d, d.value)}.`;
}

function setDraw(d: Draw) {
    const value = drawPicks.value[`${d.quarterId}:${d.key}`] ?? '';
    const text =
        value === ''
            ? `Hand this back to chance? It will be drawn ${d.when}.`
            : d.key === 'world' && d.open
              ? `Set this to "${drawLabel(d, value)}"? The quarter is open, so students will see the new world right away.`
              : `Set this to "${drawLabel(d, value)}" for the whole class?`;

    if (!window.confirm(text)) {
        return;
    }

    busy.value = true;
    router.post(
        `/faculty/quarters/${d.quarterId}/draws?section=${props.section.id}`,
        { draw: d.key, value },
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
                <ClassSwitcher
                    :classes="classes"
                    :current="section.id"
                    path="/faculty"
                />
                <span style="color: #c9d4da">{{ section.course }}</span>
            </div>
            <div class="flex gap-4 text-[13px]">
                <a
                    :href="`/faculty/roster?section=${section.id}`"
                    style="color: var(--hx-mint)"
                    >Students and teams</a
                >
                <a
                    :href="`/faculty/results.csv?section=${section.id}`"
                    style="color: var(--hx-mint)"
                    title="Every team's results, memos and feedback, one row per team per quarter"
                    >Results (CSV)</a
                >
                <a v-if="isAdmin" href="/admin" style="color: var(--hx-mint)"
                    >Admin</a
                >
                <a href="/settings/profile" style="color: var(--hx-mint)"
                    >Settings</a
                >
            </div>
        </header>

        <main class="mx-auto max-w-[1100px] px-5 py-7">
            <div v-if="quarter === null" class="hx-card">
                This class has no quarters yet.
            </div>

            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="hx-eyebrow">
                            {{ quarter.paired ? 'This week' : 'This quarter' }}
                        </p>
                        <h1 class="hx-h1 mt-1">
                            {{ quarter.paired ? 'Week' : 'Quarter' }}
                            {{ quarter.week }} · {{ quarter.label }}
                        </h1>
                        <p class="mt-1">{{ statusText[quarter.status] }}</p>
                        <p v-if="quarter.deadlineText" class="hx-hint">
                            Deadline: {{ quarter.deadlineText }} (Eastern)
                        </p>
                        <p
                            v-if="quarter.coverage"
                            class="hx-hint mt-2 max-w-[720px]"
                        >
                            {{ quarter.coverage }}
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
                            Open {{ quarter.label }}
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

                <section v-if="draws.length" class="hx-card mt-6">
                    <h2 class="hx-h2">Decided for the whole class</h2>
                    <p class="hx-hint mt-1">
                        These are drawn by chance unless you set them first.
                        Every team gets the same outcome.
                    </p>
                    <div
                        v-for="d in draws"
                        :key="`${d.quarterId}:${d.key}`"
                        class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4"
                        style="border-color: var(--hx-line)"
                    >
                        <div class="min-w-[260px] flex-1">
                            <p class="font-semibold">
                                {{ d.title }}
                                <span class="hx-hint font-normal"
                                    >· {{ d.quarterLabel }}</span
                                >
                            </p>
                            <p class="hx-hint">{{ drawStatus(d) }}</p>
                        </div>
                        <template v-if="d.settable">
                            <label class="block w-full max-w-[440px]">
                                <span class="hx-sr">{{ d.title }}</span>
                                <select
                                    v-model="
                                        drawPicks[`${d.quarterId}:${d.key}`]
                                    "
                                    class="hx-in hx-in-wide"
                                    :disabled="busy"
                                >
                                    <option value="">Leave it to chance</option>
                                    <option
                                        v-for="o in d.options"
                                        :key="o.value"
                                        :value="o.value"
                                    >
                                        {{ o.label }} ({{ o.chance }}%)
                                    </option>
                                </select>
                            </label>
                            <button
                                type="button"
                                class="hx-btn hx-btn-outline"
                                :disabled="
                                    busy ||
                                    (drawPicks[`${d.quarterId}:${d.key}`] ??
                                        '') === (d.value ?? '')
                                "
                                @click="setDraw(d)"
                            >
                                Set
                            </button>
                        </template>
                    </div>
                </section>

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
                                <th scope="col">
                                    {{
                                        quarter?.isBoard
                                            ? 'Board defense'
                                            : 'Memo'
                                    }}
                                </th>
                                <th scope="col">Advisor answers</th>
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
                                    <template v-if="quarter?.isBoard">
                                        <span
                                            v-if="t.defense"
                                            style="color: var(--hx-teal)"
                                            >Defense saved</span
                                        >
                                        <span v-else class="hx-hint"
                                            >No defense yet</span
                                        >
                                        <div class="hx-hint mt-1">
                                            {{
                                                t.verdict === 'published'
                                                    ? 'Verdict sent'
                                                    : t.verdict === 'decided'
                                                      ? 'Verdict decided, not sent'
                                                      : 'No verdict yet'
                                            }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span v-if="t.memoWords > 0"
                                            >{{ t.memoWords }} words</span
                                        >
                                        <span v-else class="hx-hint">{{
                                            t.flag
                                        }}</span>
                                    </template>
                                </td>
                                <td>{{ t.advisorAnswers }}</td>
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

                                    <a
                                        v-if="feedbackQuarter"
                                        class="mt-1 block"
                                        :href="`/faculty/teams/${t.id}/quarters/${feedbackQuarter.id}/feedback?section=${section.id}`"
                                        >{{
                                            t.feedback === 'published'
                                                ? 'Feedback sent'
                                                : t.feedback === 'drafted'
                                                  ? 'Finish feedback'
                                                  : quarter?.isBoard &&
                                                      feedbackQuarter.id ===
                                                          quarter.boardQuarterId
                                                    ? 'Feedback and the verdict'
                                                    : 'Write feedback'
                                        }}
                                        · {{ feedbackQuarter.label }}</a
                                    >
                                    <a
                                        v-if="
                                            feedbackQuarter &&
                                            quarter?.isBoard &&
                                            quarter.boardQuarterId !== null &&
                                            quarter.boardQuarterId !==
                                                feedbackQuarter.id &&
                                            feedbackQuarter.id === quarter.id
                                        "
                                        class="mt-1 block"
                                        :href="`/faculty/teams/${t.id}/quarters/${quarter.boardQuarterId}/feedback?section=${section.id}`"
                                        >{{
                                            t.verdict === 'published'
                                                ? 'Verdict sent'
                                                : 'The defense and the verdict'
                                        }}
                                        · {{ quarter.boardLabel }}</a
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
