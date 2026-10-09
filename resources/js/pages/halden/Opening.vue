<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { Advisor } from '@/halden/types';

interface Person {
    initials: string;
    name: string;
    role: string;
    wants: string;
    comes_in: string;
}

interface OpeningContent {
    mandate: string;
    advisors: Advisor[];
    screens: {
        appointment: {
            step: string;
            memo_header: string;
            memo: string[];
            mandate_label: string;
            mandate_note: string;
            next: string;
        };
        company: {
            step: string;
            title: string;
            body: string;
            box_title: string;
            box: string;
            assets: { label: string; items: string[] }[];
            next: string;
        };
        timeline: {
            step: string;
            title: string;
            rows: { when: string; what: string; now?: boolean }[];
            card_title: string;
            card: string[];
            next: string;
        };
        executives: {
            step: string;
            comes_in_label: string;
            title: string;
            intro: string;
            people: Person[];
            closing: string;
            next: string;
        };
        advisors: {
            step: string;
            title: string;
            intro: string;
            next: string;
        };
        quarter: {
            step: string;
            title: string;
            intro: string;
            steps: { label: string; text: string }[];
            team_title: string;
            team: string;
            ranked_title: string;
            ranked: string;
            graded_title: string;
            graded: string;
            next: string;
        };
        endings: {
            step: string;
            title: string;
            cards: { title: string; text: string }[];
            closing: string;
            next: string;
        };
        note: {
            step: string;
            header: string[];
            body: string[];
            handwritten: string;
            choice_title: string;
            choices: { key: string; name: string; quote: string }[];
            choice_note: string;
            sentence_title: string;
            sentence_note: string;
            sentence_start: string;
            sentence_mid: string;
            example_label: string;
            example_become: string;
            example_by: string;
            evp_note: string;
            others_note: string;
            nothing_yet: string;
            empty_confirm: string;
            start: string;
        };
    };
}

const props = defineProps<{
    opening: OpeningContent;
    team: {
        name: string;
        firstMeeting: string | null;
        become: string | null;
        by: string | null;
    } | null;
    isEvp: boolean;
    canChange: boolean;
    replay: boolean;
}>();

const s = computed(() => props.opening.screens);
const steps = [
    'appointment',
    'company',
    'timeline',
    'executives',
    'advisors',
    'quarter',
    'endings',
    'note',
] as const;
const stepNames = computed(() => steps.map((k) => s.value[k].step));
const startAt = Number(
    new URLSearchParams(window.location.search).get('step') ?? '1',
);
const at = ref(
    Number.isInteger(startAt) && startAt >= 1 && startAt <= steps.length
        ? startAt - 1
        : 0,
);
const nextLabel = computed(() => {
    const screen = s.value[steps[at.value]];

    return 'next' in screen ? screen.next : '';
});

const firstMeeting = ref<string | null>(props.team?.firstMeeting ?? null);
const become = ref(props.team?.become ?? '');
const by = ref(props.team?.by ?? '');
const meetingLocked = computed(() => !props.canChange);
const sentenceLocked = computed(() => !props.canChange);
const sending = ref(false);
const errors = computed(
    () => (usePage().props.errors ?? {}) as Record<string, string>,
);

function go(n: number) {
    at.value = Math.max(0, Math.min(steps.length - 1, n));
    window.scrollTo({ top: 0 });
}

function finish() {
    if (
        props.canChange &&
        (become.value.trim() === '' || by.value.trim() === '') &&
        !window.confirm(s.value.note.empty_confirm)
    ) {
        return;
    }

    sending.value = true;
    router.post(
        '/opening',
        {
            first_meeting: meetingLocked.value ? null : firstMeeting.value,
            become: sentenceLocked.value ? null : become.value,
            by: sentenceLocked.value ? null : by.value,
        },
        { onFinish: () => (sending.value = false) },
    );
}
</script>

<template>
    <Head title="Welcome to Halden" />
    <div class="hx min-h-screen">
        <header
            class="flex flex-wrap items-center justify-between gap-3 px-7 py-3.5"
            style="background: var(--hx-night); color: #e6ecef"
        >
            <span
                class="hx-mono text-[13px] tracking-[0.2em]"
                style="color: var(--hx-mint)"
                >HALDEN</span
            >
            <span class="text-[13px]" style="color: #c9d4da">
                {{ at + 1 }} of {{ steps.length }} · {{ stepNames[at] }}
            </span>
            <a
                v-if="replay"
                href="/play"
                class="text-[13px]"
                style="color: var(--hx-mint)"
                >Back to the game</a
            >
        </header>
        <div class="flex gap-1 px-7 pt-3" aria-hidden="true">
            <span
                v-for="(_, i) in steps"
                :key="i"
                class="h-1 flex-1 rounded"
                :style="`background: ${i <= at ? 'var(--hx-teal)' : 'var(--hx-line)'}`"
            />
        </div>

        <main class="mx-auto max-w-[860px] px-5 py-8">
            <!-- 1. Appointment -->
            <section v-if="steps[at] === 'appointment'">
                <div class="hx-card">
                    <p
                        class="hx-mono text-[12px] tracking-[0.12em]"
                        style="color: var(--hx-muted)"
                    >
                        {{ s.appointment.memo_header }}
                    </p>
                    <p
                        v-for="(p, i) in s.appointment.memo"
                        :key="i"
                        class="hx-serif mt-4 text-[18px] leading-relaxed"
                    >
                        {{ p }}
                    </p>
                    <div
                        class="mt-6 rounded-md p-4"
                        style="background: var(--hx-night); color: #e6ecef"
                    >
                        <p
                            class="hx-mono text-[12px] tracking-[0.12em]"
                            style="color: var(--hx-mint)"
                        >
                            {{ s.appointment.mandate_label }}
                        </p>
                        <p class="hx-serif mt-2 text-[20px] italic">
                            {{ opening.mandate }}
                        </p>
                    </div>
                    <p class="hx-hint mt-3">{{ s.appointment.mandate_note }}</p>
                </div>
            </section>

            <!-- 2. Company -->
            <section v-else-if="steps[at] === 'company'">
                <h1 class="hx-h1">{{ s.company.title }}</h1>
                <p class="hx-p mt-3 text-[17px]" style="color: var(--hx-ink)">
                    {{ s.company.body }}
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="a in s.company.assets"
                        :key="a.label"
                        class="hx-card"
                    >
                        <p class="hx-eyebrow">{{ a.label }}</p>
                        <ul class="mt-2 list-disc pl-5">
                            <li v-for="it in a.items" :key="it">{{ it }}</li>
                        </ul>
                    </div>
                </div>
                <div
                    class="mt-5 rounded-md p-4"
                    style="
                        background: var(--hx-teal-wash);
                        border: 1px solid var(--hx-teal-soft);
                    "
                >
                    <p class="font-semibold">{{ s.company.box_title }}</p>
                    <p class="mt-1">{{ s.company.box }}</p>
                </div>
            </section>

            <!-- 3. Timeline -->
            <section v-else-if="steps[at] === 'timeline'">
                <h1 class="hx-h1">{{ s.timeline.title }}</h1>
                <ol class="mt-5">
                    <li
                        v-for="r in s.timeline.rows"
                        :key="r.when"
                        class="grid grid-cols-[110px_1fr] gap-4 border-b py-3"
                        style="border-color: var(--hx-line)"
                    >
                        <span
                            class="hx-mono text-[13px]"
                            :style="
                                r.now
                                    ? 'color: var(--hx-teal); font-weight: 700'
                                    : 'color: var(--hx-muted)'
                            "
                            >{{ r.when }}</span
                        >
                        <span :class="r.now ? 'font-semibold' : ''">{{
                            r.what
                        }}</span>
                    </li>
                </ol>
                <div class="hx-card mt-5">
                    <p class="font-semibold">{{ s.timeline.card_title }}</p>
                    <p v-for="(p, i) in s.timeline.card" :key="i" class="mt-2">
                        {{ p }}
                    </p>
                </div>
            </section>

            <!-- 4. Executives -->
            <section v-else-if="steps[at] === 'executives'">
                <h1 class="hx-h1">{{ s.executives.title }}</h1>
                <p class="hx-p mt-3 text-[17px]" style="color: var(--hx-ink)">
                    {{ s.executives.intro }}
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="p in s.executives.people"
                        :key="p.name"
                        class="hx-card"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="hx-mono flex h-10 w-10 items-center justify-center rounded-full text-[13px]"
                                style="
                                    background: var(--hx-night);
                                    color: var(--hx-mint);
                                "
                                aria-hidden="true"
                                >{{ p.initials }}</span
                            >
                            <div>
                                <p class="font-semibold">{{ p.name }}</p>
                                <p class="hx-hint">{{ p.role }}</p>
                            </div>
                        </div>
                        <p class="mt-3">{{ p.wants }}</p>
                        <p
                            class="mt-3 text-[14px]"
                            style="color: var(--hx-muted)"
                        >
                            <span class="font-semibold">{{
                                s.executives.comes_in_label
                            }}</span>
                            {{ p.comes_in }}
                        </p>
                    </div>
                </div>
                <p class="hx-serif mt-5 text-[18px] italic">
                    {{ s.executives.closing }}
                </p>
            </section>

            <!-- 5. Advisors -->
            <section v-else-if="steps[at] === 'advisors'">
                <h1 class="hx-h1">{{ s.advisors.title }}</h1>
                <p class="hx-p mt-3 text-[17px]" style="color: var(--hx-ink)">
                    {{ s.advisors.intro }}
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="a in opening.advisors"
                        :key="a.name"
                        class="hx-card"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="hx-mono flex h-10 w-10 items-center justify-center rounded-full text-[13px]"
                                style="
                                    background: var(--hx-teal-wash);
                                    color: var(--hx-teal);
                                "
                                aria-hidden="true"
                                >{{ a.initials }}</span
                            >
                            <div>
                                <p class="font-semibold">{{ a.name }}</p>
                                <p class="hx-hint">{{ a.role }}</p>
                            </div>
                        </div>
                        <p class="mt-3">
                            <span class="font-semibold">Good at:</span>
                            {{ a.good_at }}
                        </p>
                        <p class="mt-1">
                            <span class="font-semibold">Watch out for:</span>
                            {{ a.watch_out }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- 6. How a quarter works -->
            <section v-else-if="steps[at] === 'quarter'">
                <h1 class="hx-h1">{{ s.quarter.title }}</h1>
                <p class="hx-p mt-3 text-[17px]" style="color: var(--hx-ink)">
                    {{ s.quarter.intro }}
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="st in s.quarter.steps"
                        :key="st.label"
                        class="hx-card"
                    >
                        <p
                            class="hx-mono text-[12px] tracking-[0.1em]"
                            style="color: var(--hx-teal)"
                        >
                            {{ st.label }}
                        </p>
                        <p class="mt-2">{{ st.text }}</p>
                    </div>
                </div>
                <div class="hx-card mt-4">
                    <p class="font-semibold">{{ s.quarter.team_title }}</p>
                    <p class="mt-1">{{ s.quarter.team }}</p>
                    <p class="mt-4 font-semibold">
                        {{ s.quarter.ranked_title }}
                    </p>
                    <p class="mt-1">{{ s.quarter.ranked }}</p>
                    <p class="mt-4 font-semibold">
                        {{ s.quarter.graded_title }}
                    </p>
                    <p class="mt-1">{{ s.quarter.graded }}</p>
                </div>
            </section>

            <!-- 7. Endings -->
            <section v-else-if="steps[at] === 'endings'">
                <h1 class="hx-h1">{{ s.endings.title }}</h1>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="c in s.endings.cards"
                        :key="c.title"
                        class="hx-card"
                    >
                        <p class="hx-serif text-[18px] font-semibold">
                            {{ c.title }}
                        </p>
                        <p class="mt-2">{{ c.text }}</p>
                    </div>
                </div>
                <p class="hx-serif mt-5 text-[18px] italic">
                    {{ s.endings.closing }}
                </p>
            </section>

            <!-- 8. Priya's note -->
            <section v-else>
                <div class="hx-card">
                    <p
                        v-for="h in s.note.header"
                        :key="h"
                        class="hx-mono text-[12px]"
                        style="color: var(--hx-muted)"
                    >
                        {{ h }}
                    </p>
                    <p v-for="(p, i) in s.note.body" :key="i" class="mt-4">
                        {{ p }}
                    </p>
                    <p
                        class="hx-serif mt-4 text-[18px] italic"
                        style="color: var(--hx-teal)"
                    >
                        {{ s.note.handwritten }}
                    </p>
                </div>

                <h2 class="hx-h2 mt-7">{{ s.note.choice_title }}</h2>
                <p v-if="team === null" class="hx-hint mt-1">
                    You're not on a team yet, so your instructor will set this
                    up.
                </p>
                <p
                    v-else
                    class="mt-2 rounded-md px-3 py-2 text-[14px]"
                    style="background: var(--hx-teal-wash)"
                >
                    {{ canChange ? s.note.evp_note : s.note.others_note }}
                </p>
                <div
                    class="mt-3 grid gap-3 sm:grid-cols-2"
                    role="group"
                    :aria-label="s.note.choice_title"
                >
                    <button
                        v-for="c in s.note.choices"
                        :key="c.key"
                        type="button"
                        class="hx-opt text-left"
                        :aria-pressed="firstMeeting === c.key"
                        :disabled="meetingLocked || team === null"
                        @click="firstMeeting = c.key"
                    >
                        <span class="block font-semibold">{{ c.name }}</span>
                        <span class="hx-serif mt-1 block italic">{{
                            c.quote
                        }}</span>
                    </button>
                </div>
                <p class="hx-hint mt-2">{{ s.note.choice_note }}</p>

                <h2 class="hx-h2 mt-7">{{ s.note.sentence_title }}</h2>
                <p class="hx-hint mt-1">{{ s.note.sentence_note }}</p>
                <div
                    class="hx-serif mt-3 flex flex-wrap items-center gap-2 text-[18px]"
                >
                    <span>{{ s.note.sentence_start }}</span>
                    <label class="hx-sr" for="become"
                        >What Halden should become</label
                    >
                    <input
                        id="become"
                        v-model="become"
                        class="hx-in hx-in-wide min-w-[260px] flex-1"
                        :disabled="sentenceLocked || team === null"
                        maxlength="300"
                    />
                    <span>{{ s.note.sentence_mid }}</span>
                    <label class="hx-sr" for="by">How it gets there</label>
                    <input
                        id="by"
                        v-model="by"
                        class="hx-in hx-in-wide min-w-[260px] flex-1"
                        :disabled="sentenceLocked || team === null"
                        maxlength="300"
                    />
                </div>
                <p v-if="canChange" class="hx-hint mt-2">
                    {{ s.note.example_label }} “{{ s.note.sentence_start }}
                    {{ s.note.example_become }} {{ s.note.sentence_mid }}
                    {{ s.note.example_by }}.”
                </p>
                <p v-else-if="team && !team.become" class="hx-hint mt-2">
                    {{ s.note.nothing_yet }}
                </p>
                <p v-for="(e, k) in errors" :key="k" class="hx-error mt-2">
                    {{ e }}
                </p>
            </section>

            <div class="mt-8 flex items-center justify-between gap-3">
                <button
                    v-if="at > 0"
                    type="button"
                    class="hx-btn hx-btn-outline"
                    @click="go(at - 1)"
                >
                    Back
                </button>
                <span v-else />
                <button
                    v-if="at < steps.length - 1"
                    type="button"
                    class="hx-btn hx-btn-primary"
                    @click="go(at + 1)"
                >
                    {{ nextLabel }}
                </button>
                <button
                    v-else
                    type="button"
                    class="hx-btn hx-btn-primary"
                    :disabled="sending"
                    @click="finish"
                >
                    {{ s.note.start }}
                </button>
            </div>
        </main>
    </div>
</template>
