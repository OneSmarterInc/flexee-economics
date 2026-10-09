<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AdvisorsPanel from '@/halden/AdvisorsPanel.vue';
import HelpPanel from '@/halden/HelpPanel.vue';
import type {
    DecisionMap,
    DecisionValue,
    Lever,
    PlayProps,
} from '@/halden/types';

const props = defineProps<PlayProps>();
const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

type Section =
    | 'briefing'
    | 'prices'
    | 'advisors'
    | 'question'
    | 'oil_fields'
    | 'refineries'
    | 'gas_stations'
    | 'trading_finance'
    | 'capital'
    | 'memo'
    | 'check'
    | 'story'
    | 'pnl'
    | 'score'
    | 'earlier'
    | 'news'
    | 'people';

const resultSections: Section[] = [
    'story',
    'pnl',
    'score',
    'earlier',
    'news',
    'people',
];
const allSections: Section[] = [
    'briefing',
    'prices',
    'advisors',
    'question',
    'oil_fields',
    'refineries',
    'gas_stations',
    'trading_finance',
    'capital',
    'memo',
    'check',
    ...resultSections,
];

function initialSection(): Section {
    if (allSections.includes(props.startPage as Section)) {
        return props.startPage as Section;
    }

    return props.results ? 'story' : 'briefing';
}

const section = ref<Section>(initialSection());
const draft = reactive<DecisionMap>({ ...props.decisions.current });
const memoText = ref(props.memo.text);
const savedNote = ref<Record<string, string>>({});
const saving = ref<string | null>(null);

watch(
    () => props.decisions.current,
    (next) => Object.assign(draft, next),
);
watch(
    () => props.memo.text,
    (next) => {
        memoText.value = next;
    },
);

function go(s: Section): void {
    section.value = s;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

const leverMap = computed<Record<string, Lever>>(() =>
    Object.fromEntries(props.decisions.levers.map((l) => [l.key, l])),
);

function lever(key: string): Lever {
    return leverMap.value[key];
}

function rangeOf(key: string, signed = false): string {
    const l = lever(key);
    const show = (v: number | null) =>
        v === null
            ? ''
            : signed && v > 0
              ? `+${v}`
              : String(v).replace('-', '−');

    return `${show(l.min)} to ${show(l.max)}`;
}

function isOpenLever(key: string): boolean {
    return lever(key)?.isOpen ?? false;
}

function badge(key: string): string {
    return props.leverText.badges[lever(key)?.tier ?? 'your_call'] ?? '';
}

function badgeClass(key: string): string {
    const tier = lever(key)?.tier ?? 'your_call';

    return tier === 'your_call' ? 'hx-badge-call' : 'hx-badge-agree';
}

function num(v: DecisionValue | undefined): number {
    return typeof v === 'number' ? v : Number(v ?? 0);
}

function fmt(v: number, digits = 0): string {
    return v.toLocaleString('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
}

function money(m: number): string {
    const sign = m < 0 ? '−' : '';

    return `${sign}$${fmt(Math.abs(m), Math.abs(m) < 10 ? 1 : 0)}M`;
}

function signedMoney(m: number): string {
    if (Math.abs(m) < 0.05) {
        return 'No change';
    }

    return `${m >= 0 ? '+' : '−'}$${fmt(Math.abs(m), Math.abs(m) < 10 ? 1 : 0)}M`;
}

function cents(v: DecisionValue | undefined): string {
    const n = num(v);

    return `${n > 0 ? '+' : n < 0 ? '−' : ''}${fmt(Math.abs(n), 1)}`;
}

function timeOf(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('en-US', {
        weekday: 'short',
        hour: 'numeric',
        minute: '2-digit',
    });
}

const editable = computed(() => props.canEdit);
const isOpenQuarter = computed(() => props.quarter.status === 'open');

const pageKeys: Record<string, string[]> = {
    oil_fields: ['rigs', 'norway'],
    refineries: ['br_run', 'rot_posture', 'rot_run', 'sg_request'],
    gas_stations: [
        'off_urban',
        'off_suburban',
        'off_rural',
        'off_interstate',
        'off_nl',
        'off_be',
        'off_de',
    ],
    trading_finance: [
        'tp_method',
        'tp_value',
        'crude_hedge',
        'eur_hedge',
        'nok_hedge',
    ],
    capital: ['proj_br_upgrade', 'proj_rot_upgrade', 'proj_helix'],
};

function savePage(p: string): void {
    const payload: DecisionMap = {};
    for (const key of pageKeys[p]) {
        const base = key.startsWith('tp_') ? 'tp' : key;
        if (isOpenLever(base)) {
            payload[key] = draft[key] ?? null;
        }
    }
    saving.value = p;
    router.post(`/play/${props.quarter.id}/page/${p}`, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            savedNote.value = {
                ...savedNote.value,
                [p]: `Saved at ${timeOf(new Date().toISOString())}.`,
            };
        },
        onFinish: () => {
            saving.value = null;
        },
    });
}

function saveMemo(): void {
    saving.value = 'memo';
    router.post(
        `/play/${props.quarter.id}/memo`,
        { memo: memoText.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                savedNote.value = {
                    ...savedNote.value,
                    memo: `Saved at ${timeOf(new Date().toISOString())}.`,
                };
            },
            onFinish: () => {
                saving.value = null;
            },
        },
    );
}

function toggleReady(): void {
    router.post(
        `/play/${props.quarter.id}/ready`,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function setTpMethod(method: string): void {
    draft.tp_method = method;
    if (method === 'cost') {
        draft.tp_value = null;
    }
    if (method === 'market') {
        draft.tp_value = null;
    }
}

const memoWords = computed(() => {
    const t = memoText.value.trim();

    return t === '' ? 0 : t.split(/\s+/).length;
});

const texasNext = computed(() => {
    if (props.desk.permianNow === null) {
        return null;
    }
    const rigs = Math.max(0, Math.min(40, Math.round(num(draft.rigs))));

    return (
        props.desk.permianNow * (1 - props.desk.decline) + props.desk.adds[rigs]
    );
});

const refineryCrude = computed(() => {
    const br = (num(draft.br_run) / 100) * props.desk.brCapacity;
    const rot =
        draft.rot_posture === 'run'
            ? (num(draft.rot_run) / 100) * props.desk.rotCapacity
            : 0;

    return br + rot;
});

const deadlinePassed = computed(
    () =>
        props.quarter.deadline !== null &&
        new Date(props.quarter.deadline) < new Date(),
);

const pageChanged = (p: string): boolean => p in props.decisions.savedPages;

const checkRows = computed(() =>
    props.pages.map((p) => ({
        page: p,
        title: props.leverText.pages[p]?.title ?? p,
        changed: pageChanged(p),
        who: props.decisions.savedPages[p]
            ? `${props.decisions.savedPages[p].by}, ${timeOf(props.decisions.savedPages[p].at)}`
            : '—',
    })),
);

const commitText = computed(() => {
    const parts = [
        `${num(draft.rigs)} rigs in Texas`,
        `Norway ${draft.norway === 'cut' ? 'pumping 10% less' : 'pumping as planned'}`,
        `Baton Rouge at ${num(draft.br_run)}%`,
        draft.rot_posture === 'run'
            ? `Rotterdam at ${num(draft.rot_run)}%`
            : draft.rot_posture === 'idle'
              ? 'Rotterdam paused'
              : 'Rotterdam closed',
    ];
    if (isOpenLever('sg_request')) {
        parts.push(`asking Singapore for ${num(draft.sg_request)}%`);
    }
    if (isOpenLever('tp')) {
        parts.push(
            draft.tp_method === 'other'
                ? `Baton Rouge pays $${num(draft.tp_value).toFixed(2)} a barrel for Texas crude`
                : draft.tp_method === 'cost'
                  ? 'Baton Rouge pays what the crude costs to pump and ship'
                  : 'Baton Rouge pays the market price for Texas crude',
        );
    }

    return parts.join(' · ');
});

const stationKeys = pageKeys.gas_stations;
const hedgeKeys = ['crude_hedge', 'eur_hedge', 'nok_hedge'];

function hedgeText(key: string, v: DecisionValue | undefined): string {
    const n = num(v);

    if (n === 0) {
        return 'none';
    }

    return lever(key).unit === 'pct' ? `${fmt(n)}%` : `$${fmt(n)}M`;
}

const newOutlay = computed(() =>
    (props.desk.capital?.projects ?? []).reduce(
        (sum, pr) =>
            draft[`proj_${pr.key}`] === 'commit' &&
            !(props.desk.capital?.committedBefore ?? []).includes(pr.key)
                ? sum + pr.outlay
                : sum,
        0,
    ),
);

const groupOf: Partial<Record<Section, number>> = {
    briefing: 0,
    prices: 0,
    question: 0,
    advisors: 1,
    memo: 2,
    check: 3,
    oil_fields: 3,
    refineries: 3,
    gas_stations: 3,
    trading_finance: 3,
    capital: 3,
};

const rail = computed(() => [
    {
        label: '1 · Read up',
        to: 'briefing' as Section,
        on: groupOf[section.value] === 0,
    },
    {
        label: '2 · Ask your advisors',
        to: 'advisors' as Section,
        on: groupOf[section.value] === 1,
    },
    {
        label: '3 · Agree as a team',
        to: 'memo' as Section,
        on: groupOf[section.value] === 2,
    },
    {
        label: '4 · Make your decisions',
        to: 'check' as Section,
        on: groupOf[section.value] === 3,
    },
]);

const navGroups = computed(() => [
    {
        title: 'This quarter',
        items: [
            { key: 'briefing' as Section, label: "What's happening" },
            { key: 'prices' as Section, label: 'Prices' },
            { key: 'advisors' as Section, label: 'Your advisors' },
            { key: 'question' as Section, label: 'The big question' },
            { key: 'memo' as Section, label: 'Your memo' },
            { key: 'check' as Section, label: 'Check and submit' },
        ],
    },
    {
        title: 'Your decisions',
        items: props.pages.map((p) => ({
            key: p as Section,
            label: props.leverText.pages[p]?.title ?? p,
            tag: props.decisions.levers.some((l) => l.page === p && l.isNew)
                ? 'new'
                : '',
        })),
    },
    {
        title: 'Your results',
        items: [
            { key: 'story' as Section, label: 'What happened' },
            { key: 'pnl' as Section, label: 'Profit and loss' },
            { key: 'score' as Section, label: 'Your score' },
            { key: 'earlier' as Section, label: 'Earlier choices' },
            { key: 'news' as Section, label: 'Industry news' },
            { key: 'people' as Section, label: 'Where you stand' },
        ].map((i) => ({ ...i, tag: props.results ? '' : 'after the close' })),
    },
]);

const showResults = computed(
    () => resultSections.includes(section.value) && props.results !== null,
);
const resultsLocked = computed(
    () => resultSections.includes(section.value) && props.results === null,
);

const wtiMax = computed(() => Math.max(...props.wti.map((w) => w.value), 1));
const wtiMin = computed(() =>
    Math.min(...props.wti.map((w) => w.value), wtiMax.value - 1),
);

const bridgeScale = computed(() => {
    if (!props.results) {
        return 1;
    }

    return Math.max(
        1,
        ...props.results.bridge.parts.map((p) => Math.abs(p.value)),
    );
});

function kpiValue(unit: string, v: number | null): string {
    if (v === null) {
        return '—';
    }
    switch (unit) {
        case 'usd2':
            return `${v < 0 ? '−' : ''}$${fmt(Math.abs(v), 2)}`;
        case 'pct':
            return `${fmt(v, 1)}%`;
        case 'musd':
            return money(v);
        case 'kusd':
            return `$${fmt(v, 1)}k`;
        case 'x':
            return `${fmt(v, 2)}x`;
        default:
            return fmt(v, 1);
    }
}

function quarterHref(id: number): string {
    return props.readOnly
        ? `${window.location.pathname.replace(/quarters\/\d+$/, `quarters/${id}`)}`
        : `/play/${id}`;
}
</script>

<template>
    <Head :title="`${quarter.label} · ${team.name}`" />
    <div class="hx">
        <header
            class="flex flex-wrap items-center justify-between gap-x-7 gap-y-3 px-7 py-3.5"
            style="background: var(--hx-night); color: #e6ecef"
        >
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1.5">
                <span
                    class="hx-mono text-[13px] tracking-[0.2em]"
                    style="color: var(--hx-mint)"
                    >HALDEN</span
                >
                <span class="font-semibold">{{ team.name }}</span>
                <span
                    >{{ quarter.label }} · Quarter {{ quarter.number }} of
                    {{ quarter.total }}</span
                >
                <span
                    class="rounded px-2 py-0.5 text-[13px]"
                    :style="
                        quarter.status === 'open'
                            ? 'background: var(--hx-mint); color: #0f1a22; font-weight: 600'
                            : 'background: #2a3b47; color: #e6ecef'
                    "
                    >{{
                        quarter.status === 'open'
                            ? 'Open'
                            : quarter.status === 'published'
                              ? 'Closed · results are in'
                              : quarter.status === 'closed'
                                ? 'Closed'
                                : 'Not open yet'
                    }}</span
                >
                <span style="color: #c9d4da">{{ quarter.deadlineText }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <nav aria-label="Quarters" class="flex flex-wrap gap-1.5">
                    <a
                        v-for="q in quarters.filter(
                            (q) => q.status !== 'upcoming',
                        )"
                        :key="q.id"
                        :href="quarterHref(q.id)"
                        class="rounded px-2 py-1 text-[12px] no-underline"
                        :style="
                            q.id === quarter.id
                                ? 'background: #2a3b47; color: #fff'
                                : 'color: #c9d4da'
                        "
                        >Q{{ q.number }}</a
                    >
                </nav>
                <HelpPanel :help="help" />
            </div>
        </header>
        <div
            class="hx-serif px-7 py-2 text-[15px] italic"
            style="background: var(--hx-night-2); color: #c9d4da"
        >
            What the CEO wants: {{ mandate }}
        </div>
        <div
            v-if="readOnly"
            class="mx-7 mt-3 rounded-md px-3 py-2 text-[13px]"
            style="
                background: #fff4e5;
                border: 1px solid #e9c48f;
                color: #6a4410;
            "
        >
            You're viewing {{ team.name }}'s screens as they see them. Nothing
            can be changed here.
        </div>

        <div class="flex flex-wrap items-center gap-2.5 px-7 pt-3.5">
            <button
                v-for="r in rail"
                :key="r.label"
                type="button"
                class="min-h-[38px] cursor-pointer rounded-full px-3.5 py-2 text-[13px] font-semibold"
                :style="
                    r.on
                        ? 'background: var(--hx-teal); color: #fff; border: 1px solid var(--hx-teal)'
                        : 'background: #fff; color: var(--hx-ink-2); border: 1px solid #c3cbc6'
                "
                @click="go(r.to)"
            >
                {{ r.label }}
            </button>
        </div>

        <div class="flex flex-wrap items-start gap-6 px-7 pt-4 pb-10">
            <nav
                aria-label="Quarter menu"
                class="flex max-w-[260px] flex-[1_1_220px] flex-col gap-4"
            >
                <div v-for="g in navGroups" :key="g.title">
                    <div class="hx-eyebrow mb-1.5">{{ g.title }}</div>
                    <div class="flex flex-col gap-0.5">
                        <button
                            v-for="it in g.items"
                            :key="it.key"
                            type="button"
                            class="flex min-h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-md px-2.5 py-2 text-left text-[14px]"
                            :style="
                                section === it.key
                                    ? 'background: var(--hx-teal-soft); color: var(--hx-teal-deep); font-weight: 600'
                                    : 'background: transparent; color: var(--hx-ink)'
                            "
                            :aria-current="
                                section === it.key ? 'page' : undefined
                            "
                            @click="go(it.key)"
                        >
                            <span>{{ it.label }}</span>
                            <span
                                v-if="'tag' in it && it.tag"
                                class="text-[11px]"
                                style="color: #8a6a3a"
                                >{{ it.tag }}</span
                            >
                        </button>
                    </div>
                </div>
                <div>
                    <div class="hx-eyebrow mb-1.5">Your team</div>
                    <div
                        v-for="m in team.members"
                        :key="m.name"
                        class="text-[13px] leading-6"
                    >
                        <span :class="m.isMe ? 'font-semibold' : ''">{{
                            m.name
                        }}</span>
                        <span class="hx-hint"> · {{ m.seat }}</span>
                    </div>
                </div>
            </nav>

            <main class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                <!-- What's happening -->
                <template v-if="section === 'briefing'">
                    <div v-if="!content" class="hx-card">
                        <h1 class="hx-h1">
                            This quarter hasn't been written yet
                        </h1>
                        <p class="hx-p">
                            Your instructor will open it when it's ready.
                        </p>
                    </div>
                    <template v-else>
                        <div
                            class="hx-card"
                            style="
                                background: var(--hx-teal-wash);
                                border-color: #bfdcd6;
                            "
                        >
                            <div
                                class="hx-eyebrow mb-1.5"
                                style="color: var(--hx-teal)"
                            >
                                {{
                                    quarter.number > 1 && carrying?.text
                                        ? carrying.title
                                        : 'Where you left off'
                                }}
                            </div>
                            <p v-if="quarter.number === 1" class="hx-p m-0">
                                This is your first quarter. Everything the old
                                presidents set up is still running.
                                <span v-if="team.strategy">
                                    Your team's plan: {{ team.strategy }}</span
                                >
                                <span v-else-if="me?.seat === 'evp'">
                                    Your team's sentence isn't recorded yet.
                                    <a href="/opening?step=8">Record it now</a
                                    >.</span
                                >
                                <span v-else>
                                    Your EVP hasn't recorded your team's
                                    sentence yet.</span
                                >
                                <a
                                    v-if="
                                        team.strategy &&
                                        me?.seat === 'evp' &&
                                        quarter.status === 'open'
                                    "
                                    href="/opening?step=8"
                                    class="ml-1"
                                    >Change it</a
                                >
                            </p>
                            <template v-else-if="carrying">
                                <p v-if="carrying.text" class="hx-p m-0">
                                    {{ carrying.text }}
                                </p>
                                <p
                                    v-if="carrying.reason"
                                    class="mt-1 text-[13px]"
                                    style="color: var(--hx-amber-text)"
                                >
                                    Not shown to the team: {{ carrying.reason }}
                                </p>
                            </template>
                            <p v-else class="hx-p m-0">
                                {{
                                    team.strategy ??
                                    "Your team hasn't written its sentence about what Halden should become yet."
                                }}
                                Last quarter's settings are still running unless
                                you change them.
                            </p>
                        </div>
                        <div class="hx-card">
                            <div class="hx-eyebrow mb-2">
                                {{ content.briefing.dateline }}
                            </div>
                            <h1 class="hx-h1 mb-3.5">
                                {{ content.briefing.headline }}
                            </h1>
                            <p
                                v-for="(p, i) in content.briefing.paragraphs"
                                :key="i"
                                class="hx-p"
                            >
                                {{ p }}
                            </p>
                            <div
                                class="mt-4 rounded-lg px-4 py-3.5"
                                style="
                                    background: var(--hx-ink);
                                    color: #f4f7f8;
                                "
                            >
                                <div
                                    class="hx-eyebrow mb-1"
                                    style="color: var(--hx-mint)"
                                >
                                    This quarter's big question
                                </div>
                                <div class="hx-serif text-[20px]">
                                    {{ content.briefing.question }}
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2.5">
                                <button
                                    type="button"
                                    class="hx-btn hx-btn-primary"
                                    @click="go('prices')"
                                >
                                    Next: this quarter's prices
                                </button>
                                <button
                                    type="button"
                                    class="hx-btn hx-btn-outline"
                                    @click="go('question')"
                                >
                                    See the big question and the data
                                </button>
                            </div>
                        </div>
                    </template>
                </template>

                <!-- Prices -->
                <template v-if="section === 'prices'">
                    <div class="hx-card">
                        <h1 class="hx-h1">Prices this quarter</h1>
                        <div class="hx-hint mb-3.5">
                            This quarter's prices, with last quarter's next to
                            them.
                        </div>
                        <div class="overflow-x-auto">
                            <table class="hx-tbl">
                                <thead>
                                    <tr>
                                        <th>Price</th>
                                        <th>Last quarter</th>
                                        <th>This quarter</th>
                                        <th>What it means</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="m in market" :key="m.name">
                                        <td class="font-medium">
                                            {{ m.name }}
                                        </td>
                                        <td
                                            class="hx-mono"
                                            style="color: var(--hx-muted)"
                                        >
                                            {{
                                                m.last === null
                                                    ? '—'
                                                    : m.unit === 'usd'
                                                      ? `$${fmt(m.last, 2)}`
                                                      : fmt(
                                                            m.last,
                                                            m.name === 'Euro'
                                                                ? 4
                                                                : 2,
                                                        )
                                            }}
                                        </td>
                                        <td class="hx-mono font-semibold">
                                            {{
                                                m.unit === 'usd'
                                                    ? `$${fmt(m.now, 2)}`
                                                    : fmt(
                                                          m.now,
                                                          m.name === 'Euro'
                                                              ? 4
                                                              : 2,
                                                      )
                                            }}
                                        </td>
                                        <td class="hx-hint">{{ m.what }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="hx-card">
                        <h2 class="hx-h2">US oil price, last two years</h2>
                        <div class="flex h-[150px] items-end gap-2.5 pt-2.5">
                            <div
                                v-for="w in wti"
                                :key="w.label"
                                class="flex flex-1 flex-col items-center gap-1.5"
                            >
                                <div
                                    class="hx-mono text-[11px]"
                                    style="color: var(--hx-muted)"
                                >
                                    {{ fmt(w.value, 1) }}
                                </div>
                                <div
                                    class="w-full max-w-[48px] rounded-t"
                                    :style="{
                                        height: `${20 + ((w.value - wtiMin) / Math.max(1, wtiMax - wtiMin)) * 80}px`,
                                        background: w.current
                                            ? 'var(--hx-teal)'
                                            : '#a9c9c3',
                                    }"
                                ></div>
                                <div
                                    class="hx-mono text-[11px]"
                                    style="color: var(--hx-muted)"
                                >
                                    {{ w.label }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="content" class="hx-card flex gap-4">
                        <div
                            class="flex h-11 w-11 flex-none items-center justify-center rounded-full font-semibold"
                            style="
                                background: var(--hx-teal-soft);
                                color: var(--hx-teal);
                            "
                        >
                            EM
                        </div>
                        <div>
                            <div class="font-semibold">
                                Elena Marchetti, Chief Economist · what she's
                                watching
                            </div>
                            <p class="hx-p mt-1.5">{{ content.marchetti }}</p>
                        </div>
                    </div>
                </template>

                <!-- Advisors -->
                <template v-if="section === 'advisors'">
                    <AdvisorsPanel
                        :advisors="advisors"
                        :quarter-id="quarter.id"
                        :can-ask="!readOnly && me !== null"
                        :for-faculty="readOnly && me === null"
                        @go-decide="go(pages[0] as Section)"
                    />
                </template>

                <!-- The big question -->
                <template v-if="section === 'question' && content">
                    <div class="hx-card">
                        <div class="hx-eyebrow mb-1.5">
                            The big question · Quarter {{ quarter.number }}
                        </div>
                        <h1 class="hx-h1 mb-3">
                            {{ content.briefing.question }}
                        </h1>
                        <div
                            class="mb-4 rounded-lg px-4 py-3.5"
                            style="
                                background: var(--hx-soft);
                                border: 1px solid var(--hx-line);
                            "
                        >
                            <div class="mb-1 font-semibold">How it works</div>
                            <p class="hx-p m-0">{{ content.rule }}</p>
                        </div>
                        <h2 class="hx-h2">The data</h2>
                        <div class="mb-4 flex flex-col gap-2">
                            <div
                                v-for="x in content.exhibits"
                                :key="x.url"
                                class="flex flex-wrap justify-between gap-2.5 rounded-lg px-3 py-2.5"
                                style="border: 1px solid #eceeea"
                            >
                                <span class="font-medium">{{ x.title }}</span>
                                <a :href="x.url" class="text-[14px]"
                                    >Download</a
                                >
                            </div>
                        </div>
                        <div
                            class="rounded-lg px-3.5 py-3"
                            style="
                                background: #fff4e5;
                                border: 1px solid #e9c48f;
                            "
                        >
                            <span class="hx-badge hx-badge-new">NEW</span>
                            <span class="ml-2">{{ content.newPagesNote }}</span>
                        </div>
                    </div>
                </template>

                <!-- Oil fields -->
                <template v-if="section === 'oil_fields'">
                    <div class="hx-card">
                        <div class="flex flex-wrap justify-between gap-2">
                            <h1 class="hx-h1">Oil fields</h1>
                            <span class="hx-hint">{{
                                decisions.savedPages.oil_fields
                                    ? `${decisions.savedPages.oil_fields.by} last saved ${timeOf(decisions.savedPages.oil_fields.at)}`
                                    : 'Not changed this quarter'
                            }}</span>
                        </div>
                        <div class="mt-3 flex flex-col gap-4">
                            <div class="hx-lever">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[16px] font-semibold">{{
                                        lever('rigs').label
                                    }}</span>
                                    <span
                                        class="hx-badge"
                                        :class="badgeClass('rigs')"
                                        >{{ badge('rigs') }}</span
                                    >
                                </div>
                                <div class="hx-hint mt-1 mb-2.5">
                                    {{ leverText.help.rigs }}
                                </div>
                                <div class="flex flex-wrap items-end gap-6">
                                    <div>
                                        <div class="hx-hint">Last quarter</div>
                                        <div
                                            class="hx-mono py-2 text-[18px]"
                                            style="color: var(--hx-muted)"
                                        >
                                            {{ num(decisions.previous.rigs) }}
                                            rigs
                                        </div>
                                    </div>
                                    <div>
                                        <label class="hx-hint" for="lv-rigs"
                                            >This quarter</label
                                        >
                                        <div>
                                            <input
                                                id="lv-rigs"
                                                v-model.number="draft.rigs"
                                                class="hx-in"
                                                type="number"
                                                :min="
                                                    lever('rigs').min ??
                                                    undefined
                                                "
                                                :max="
                                                    lever('rigs').max ??
                                                    undefined
                                                "
                                                :step="
                                                    lever('rigs').step ??
                                                    undefined
                                                "
                                                :disabled="!editable"
                                            />
                                            <span class="hx-hint ml-2"
                                                >rigs ·
                                                {{ rangeOf('rigs') }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="errors.rigs"
                                            class="hx-error"
                                        >
                                            {{ errors.rigs }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="hx-lever">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[16px] font-semibold">{{
                                        lever('norway').label
                                    }}</span>
                                    <span
                                        class="hx-badge"
                                        :class="badgeClass('norway')"
                                        >{{ badge('norway') }}</span
                                    >
                                </div>
                                <div class="hx-hint mt-1 mb-2.5">
                                    {{ leverText.help.norway }}
                                </div>
                                <div class="flex flex-wrap gap-2.5">
                                    <button
                                        v-for="(label, key) in leverText.choices
                                            .norway"
                                        :key="key"
                                        type="button"
                                        class="hx-opt"
                                        :aria-pressed="draft.norway === key"
                                        :disabled="!editable"
                                        @click="draft.norway = key"
                                    >
                                        {{ label
                                        }}<span
                                            v-if="
                                                decisions.previous.norway ===
                                                key
                                            "
                                            class="font-normal"
                                            style="color: var(--hx-muted)"
                                        >
                                            · last quarter</span
                                        >
                                    </button>
                                </div>
                            </div>
                            <div
                                v-for="f in leverText.fixed_items.oil_fields"
                                :key="f.title"
                                class="hx-lever"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[16px] font-semibold">{{
                                        f.title
                                    }}</span>
                                    <span class="hx-badge hx-badge-fixed">{{
                                        f.badge
                                    }}</span>
                                </div>
                                <div class="hx-hint mt-1">{{ f.text }}</div>
                            </div>
                        </div>
                        <div class="hx-desk mt-4">
                            <div>
                                <span class="hx-eyebrow">What this means</span>
                                <div class="hx-mono mt-1 text-[14px]">
                                    {{ num(draft.rigs) }} rigs cost
                                    {{ money(num(draft.rigs) * 40) }} this
                                    quarter.
                                    <template v-if="texasNext !== null">
                                        Texas output next quarter: about
                                        {{
                                            fmt(
                                                Math.round(texasNext / 100) *
                                                    100,
                                            )
                                        }}
                                        barrels a day.</template
                                    >
                                </div>
                            </div>
                            <button
                                v-if="editable"
                                type="button"
                                class="hx-btn hx-btn-primary"
                                :disabled="saving === 'oil_fields'"
                                @click="savePage('oil_fields')"
                            >
                                Save oil fields
                            </button>
                        </div>
                        <div class="hx-hint mt-2">
                            {{ savedNote.oil_fields }}
                        </div>
                    </div>
                </template>

                <!-- Refineries -->
                <template v-if="section === 'refineries'">
                    <div class="hx-card">
                        <div class="flex flex-wrap justify-between gap-2">
                            <h1 class="hx-h1">Refineries</h1>
                            <span class="hx-hint">{{
                                decisions.savedPages.refineries
                                    ? `${decisions.savedPages.refineries.by} last saved ${timeOf(decisions.savedPages.refineries.at)}`
                                    : 'Not changed this quarter'
                            }}</span>
                        </div>
                        <div class="mt-3 flex flex-col gap-4">
                            <div class="hx-lever">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[16px] font-semibold">{{
                                        lever('br_run').label
                                    }}</span>
                                    <span
                                        class="hx-badge"
                                        :class="badgeClass('br_run')"
                                        >{{ badge('br_run') }}</span
                                    >
                                </div>
                                <div class="hx-hint mt-1 mb-2.5">
                                    {{ leverText.help.br_run }}
                                </div>
                                <div class="flex flex-wrap items-end gap-6">
                                    <div>
                                        <div class="hx-hint">Last quarter</div>
                                        <div
                                            class="hx-mono py-2 text-[18px]"
                                            style="color: var(--hx-muted)"
                                        >
                                            {{
                                                num(decisions.previous.br_run)
                                            }}%
                                        </div>
                                    </div>
                                    <div>
                                        <label class="hx-hint" for="lv-br"
                                            >This quarter</label
                                        >
                                        <div>
                                            <input
                                                id="lv-br"
                                                v-model.number="draft.br_run"
                                                class="hx-in"
                                                type="number"
                                                :min="
                                                    lever('br_run').min ??
                                                    undefined
                                                "
                                                :max="
                                                    lever('br_run').max ??
                                                    undefined
                                                "
                                                :step="
                                                    lever('br_run').step ??
                                                    undefined
                                                "
                                                :disabled="!editable"
                                            />
                                            <span class="hx-hint ml-2"
                                                >% ·
                                                {{ rangeOf('br_run') }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="errors.br_run"
                                            class="hx-error"
                                        >
                                            {{ errors.br_run }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="hx-lever">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[16px] font-semibold"
                                        >Rotterdam</span
                                    >
                                    <span
                                        class="hx-badge"
                                        :class="badgeClass('rot_run')"
                                        >{{ badge('rot_run') }}</span
                                    >
                                    <span
                                        v-if="lever('rot_posture').isNew"
                                        class="hx-badge hx-badge-new"
                                        >NEW</span
                                    >
                                </div>
                                <div
                                    v-if="desk.rotStatus === 'closed'"
                                    class="hx-hint mt-1"
                                >
                                    Rotterdam is closed for good.
                                </div>
                                <template v-else>
                                    <div class="hx-hint mt-1 mb-2.5">
                                        {{
                                            isOpenLever('rot_posture')
                                                ? leverText.help.rot_posture
                                                : leverText.help.rot_run
                                        }}
                                    </div>
                                    <div
                                        v-if="isOpenLever('rot_posture')"
                                        class="mb-3 flex flex-wrap gap-2.5"
                                    >
                                        <button
                                            v-for="(label, key) in leverText
                                                .choices.rot_posture"
                                            :key="key"
                                            type="button"
                                            class="hx-opt"
                                            :aria-pressed="
                                                draft.rot_posture === key
                                            "
                                            :disabled="!editable"
                                            @click="draft.rot_posture = key"
                                        >
                                            {{ label
                                            }}<span
                                                v-if="
                                                    decisions.previous
                                                        .rot_posture === key
                                                "
                                                class="font-normal"
                                                style="color: var(--hx-muted)"
                                            >
                                                · last quarter</span
                                            >
                                        </button>
                                    </div>
                                    <div
                                        v-if="draft.rot_posture === 'run'"
                                        class="flex flex-wrap items-end gap-6"
                                    >
                                        <div>
                                            <div class="hx-hint">
                                                Last quarter
                                            </div>
                                            <div
                                                class="hx-mono py-2 text-[18px]"
                                                style="color: var(--hx-muted)"
                                            >
                                                {{
                                                    num(
                                                        decisions.previous
                                                            .rot_run,
                                                    )
                                                }}%
                                            </div>
                                        </div>
                                        <div>
                                            <label class="hx-hint" for="lv-rot"
                                                >How hard it runs this
                                                quarter</label
                                            >
                                            <div>
                                                <input
                                                    id="lv-rot"
                                                    v-model.number="
                                                        draft.rot_run
                                                    "
                                                    class="hx-in"
                                                    type="number"
                                                    :min="
                                                        lever('rot_run').min ??
                                                        undefined
                                                    "
                                                    :max="
                                                        lever('rot_run').max ??
                                                        undefined
                                                    "
                                                    :step="
                                                        lever('rot_run').step ??
                                                        undefined
                                                    "
                                                    :disabled="!editable"
                                                />
                                                <span class="hx-hint ml-2"
                                                    >% ·
                                                    {{
                                                        rangeOf('rot_run')
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                v-if="errors.rot_run"
                                                class="hx-error"
                                            >
                                                {{ errors.rot_run }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            draft.rot_posture === 'close' &&
                                            editable
                                        "
                                        class="hx-error"
                                    >
                                        Closing is permanent. It costs about
                                        $150 million once, and Rotterdam can't
                                        be restarted.
                                    </div>
                                </template>
                            </div>
                            <div
                                v-if="isOpenLever('sg_request')"
                                class="hx-lever"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[16px] font-semibold">{{
                                        lever('sg_request').label
                                    }}</span>
                                    <span class="hx-badge hx-badge-ask">{{
                                        badge('sg_request')
                                    }}</span>
                                    <span
                                        v-if="lever('sg_request').isNew"
                                        class="hx-badge hx-badge-new"
                                        >NEW</span
                                    >
                                </div>
                                <div class="hx-hint mt-1 mb-2.5">
                                    {{ leverText.help.sg_request }}
                                </div>
                                <div class="flex flex-wrap items-end gap-6">
                                    <div>
                                        <div class="hx-hint">Last quarter</div>
                                        <div
                                            class="hx-mono py-2 text-[18px]"
                                            style="color: var(--hx-muted)"
                                        >
                                            {{
                                                num(
                                                    decisions.previous
                                                        .sg_request,
                                                )
                                            }}%
                                        </div>
                                    </div>
                                    <div>
                                        <label class="hx-hint" for="lv-sg"
                                            >Request this quarter</label
                                        >
                                        <div>
                                            <input
                                                id="lv-sg"
                                                v-model.number="
                                                    draft.sg_request
                                                "
                                                class="hx-in"
                                                type="number"
                                                :min="
                                                    lever('sg_request').min ??
                                                    undefined
                                                "
                                                :max="
                                                    lever('sg_request').max ??
                                                    undefined
                                                "
                                                :step="
                                                    lever('sg_request').step ??
                                                    undefined
                                                "
                                                :disabled="!editable"
                                            />
                                            <span class="hx-hint ml-2"
                                                >% ·
                                                {{
                                                    rangeOf('sg_request')
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="errors.sg_request"
                                            class="hx-error"
                                        >
                                            {{ errors.sg_request }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="hx-desk mt-4">
                            <div>
                                <span class="hx-eyebrow">What this means</span>
                                <div class="hx-mono mt-1 text-[14px]">
                                    Oil through Baton Rouge and Rotterdam: about
                                    {{ fmt(refineryCrude) }} barrels a day.
                                </div>
                            </div>
                            <button
                                v-if="editable"
                                type="button"
                                class="hx-btn hx-btn-primary"
                                :disabled="saving === 'refineries'"
                                @click="savePage('refineries')"
                            >
                                Save refineries
                            </button>
                        </div>
                        <div class="hx-hint mt-2">
                            {{ savedNote.refineries }}
                        </div>
                    </div>
                </template>

                <!-- Gas stations -->
                <template v-if="section === 'gas_stations'">
                    <div class="hx-card">
                        <div class="flex flex-wrap justify-between gap-2">
                            <h1 class="hx-h1">Gas stations</h1>
                            <span class="hx-hint">{{
                                decisions.savedPages.gas_stations
                                    ? `${decisions.savedPages.gas_stations.by} last saved ${timeOf(decisions.savedPages.gas_stations.at)}`
                                    : 'Not changed this quarter'
                            }}</span>
                        </div>
                        <p class="hx-p mt-2">
                            {{ leverText.pages.gas_stations.intro }}
                        </p>
                        <div class="mb-2">
                            <span class="hx-badge hx-badge-call">{{
                                leverText.badges.your_call
                            }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="hx-tbl">
                                <thead>
                                    <tr>
                                        <th>Where</th>
                                        <th>Last quarter</th>
                                        <th>
                                            This quarter (¢ a gallon above or
                                            below the going rate)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="k in stationKeys" :key="k">
                                        <td>
                                            <div class="font-medium">
                                                {{ lever(k).label }}
                                            </div>
                                            <div class="hx-hint">
                                                {{ leverText.station_notes[k] }}
                                            </div>
                                        </td>
                                        <td
                                            class="hx-mono"
                                            style="color: var(--hx-muted)"
                                        >
                                            {{ cents(decisions.previous[k]) }}
                                        </td>
                                        <td>
                                            <label
                                                class="hx-sr"
                                                :for="`lv-${k}`"
                                                >{{ lever(k).label }}</label
                                            >
                                            <input
                                                :id="`lv-${k}`"
                                                v-model.number="draft[k]"
                                                class="hx-in"
                                                type="number"
                                                :step="
                                                    lever(k).step ?? undefined
                                                "
                                                :min="lever(k).min ?? undefined"
                                                :max="lever(k).max ?? undefined"
                                                :disabled="!editable"
                                            />
                                            <span class="hx-hint ml-2">
                                                {{ rangeOf(k, true) }}</span
                                            >
                                            <div
                                                v-if="errors[k]"
                                                class="hx-error"
                                            >
                                                {{ errors[k] }}
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="hx-desk mt-4">
                            <div>
                                <span class="hx-eyebrow">What this means</span>
                                <div class="hx-mono mt-1 text-[14px]">
                                    Changing prices doesn't cost anything up
                                    front.
                                </div>
                            </div>
                            <button
                                v-if="editable"
                                type="button"
                                class="hx-btn hx-btn-primary"
                                :disabled="saving === 'gas_stations'"
                                @click="savePage('gas_stations')"
                            >
                                Save gas stations
                            </button>
                        </div>
                        <div class="hx-hint mt-2">
                            {{ savedNote.gas_stations }}
                        </div>
                    </div>
                </template>

                <!-- Trading & finance -->
                <template v-if="section === 'trading_finance'">
                    <div class="hx-card">
                        <div class="flex flex-wrap justify-between gap-2">
                            <h1 class="hx-h1">Trading &amp; finance</h1>
                            <span class="hx-hint">{{
                                decisions.savedPages.trading_finance
                                    ? `${decisions.savedPages.trading_finance.by} last saved ${timeOf(decisions.savedPages.trading_finance.at)}`
                                    : 'Not changed this quarter'
                            }}</span>
                        </div>
                        <div class="mt-3 pb-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[16px] font-semibold">{{
                                    lever('tp').label
                                }}</span>
                                <span class="hx-badge hx-badge-agree">{{
                                    badge('tp')
                                }}</span>
                                <span
                                    v-if="lever('tp').isNew"
                                    class="hx-badge hx-badge-new"
                                    >NEW</span
                                >
                            </div>
                            <div class="hx-hint mt-1 mb-3">
                                {{ leverText.help.tp }}
                            </div>
                            <div class="mb-3 flex flex-wrap gap-2.5">
                                <button
                                    v-for="(label, key) in leverText.choices
                                        .tp_method"
                                    :key="key"
                                    type="button"
                                    class="hx-opt"
                                    :aria-pressed="draft.tp_method === key"
                                    :disabled="!editable"
                                    @click="setTpMethod(String(key))"
                                >
                                    {{ label
                                    }}<span
                                        v-if="
                                            decisions.previous.tp_method === key
                                        "
                                        class="font-normal"
                                        style="color: var(--hx-muted)"
                                    >
                                        · last quarter</span
                                    >
                                </button>
                            </div>
                            <div class="flex flex-wrap items-end gap-6">
                                <div>
                                    <div class="hx-hint">
                                        This quarter's prices
                                    </div>
                                    <div
                                        class="hx-mono py-2 text-[15px]"
                                        style="color: var(--hx-muted)"
                                    >
                                        Market ${{
                                            desk.marketTp === null
                                                ? '—'
                                                : fmt(desk.marketTp, 2)
                                        }}
                                        · Cost to pump and ship ${{
                                            fmt(desk.costTp, 2)
                                        }}
                                    </div>
                                </div>
                                <div v-if="draft.tp_method === 'other'">
                                    <label class="hx-hint" for="lv-tp"
                                        >Our price</label
                                    >
                                    <div>
                                        <input
                                            id="lv-tp"
                                            v-model.number="draft.tp_value"
                                            class="hx-in"
                                            type="number"
                                            :step="
                                                lever('tp').step ?? undefined
                                            "
                                            :min="lever('tp').min ?? undefined"
                                            :max="lever('tp').max ?? undefined"
                                            :disabled="!editable"
                                        />
                                        <span class="hx-hint ml-2"
                                            >$ a barrel ·
                                            {{ rangeOf('tp') }}</span
                                        >
                                    </div>
                                    <div
                                        v-if="errors.tp_value"
                                        class="hx-error"
                                    >
                                        {{ errors.tp_value }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="errors.tp_method" class="hx-error">
                                {{ errors.tp_method }}
                            </div>
                        </div>
                        <div
                            v-for="k in hedgeKeys.filter((x) => isOpenLever(x))"
                            :key="k"
                            class="hx-lever"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[16px] font-semibold">{{
                                    lever(k).label
                                }}</span>
                                <span class="hx-badge hx-badge-agree">{{
                                    badge(k)
                                }}</span>
                                <span
                                    v-if="lever(k).isNew"
                                    class="hx-badge hx-badge-new"
                                    >NEW</span
                                >
                            </div>
                            <div class="hx-hint mt-1 mb-3">
                                {{ leverText.help[k] }}
                            </div>
                            <div class="flex flex-wrap items-end gap-6">
                                <div>
                                    <div class="hx-hint">Last quarter</div>
                                    <div
                                        class="hx-mono py-2 text-[16px]"
                                        style="color: var(--hx-muted)"
                                    >
                                        {{
                                            hedgeText(k, decisions.previous[k])
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <label class="hx-hint" :for="`lv-${k}`"
                                        >This quarter</label
                                    >
                                    <div>
                                        <input
                                            :id="`lv-${k}`"
                                            v-model.number="draft[k]"
                                            class="hx-in"
                                            type="number"
                                            :step="lever(k).step ?? undefined"
                                            :min="lever(k).min ?? undefined"
                                            :max="lever(k).max ?? undefined"
                                            :disabled="!editable"
                                        />
                                        <span class="hx-hint ml-2"
                                            >{{
                                                lever(k).unit === 'pct'
                                                    ? '%'
                                                    : '$ million'
                                            }}
                                            · {{ rangeOf(k) }}</span
                                        >
                                    </div>
                                    <div v-if="errors[k]" class="hx-error">
                                        {{ errors[k] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="hx-desk">
                            <div>
                                <span class="hx-eyebrow">What this means</span>
                                <div class="hx-mono mt-1 text-[14px]">
                                    Geneva can trade on the gap for up to
                                    {{ fmt(desk.genevaMaxVolume) }} barrels a
                                    day.<template
                                        v-if="isOpenLever('crude_hedge')"
                                    >
                                        Hedges settle next quarter:
                                        {{
                                            hedgeText(
                                                'crude_hedge',
                                                draft.crude_hedge,
                                            )
                                        }}
                                        of next quarter's oil,
                                        {{
                                            hedgeText(
                                                'eur_hedge',
                                                draft.eur_hedge,
                                            )
                                        }}
                                        of euros sold,
                                        {{
                                            hedgeText(
                                                'nok_hedge',
                                                draft.nok_hedge,
                                            )
                                        }}
                                        of kroner bought.</template
                                    >
                                </div>
                            </div>
                            <button
                                v-if="editable"
                                type="button"
                                class="hx-btn hx-btn-primary"
                                :disabled="saving === 'trading_finance'"
                                @click="savePage('trading_finance')"
                            >
                                Save trading &amp; finance
                            </button>
                        </div>
                        <div class="hx-hint mt-2">
                            {{ savedNote.trading_finance }}
                        </div>
                    </div>
                </template>

                <!-- Big projects -->
                <template v-if="section === 'capital' && desk.capital">
                    <div class="hx-card">
                        <div class="flex flex-wrap justify-between gap-2">
                            <h1 class="hx-h1">Big projects</h1>
                            <span class="hx-hint">{{
                                decisions.savedPages.capital
                                    ? `${decisions.savedPages.capital.by} last saved ${timeOf(decisions.savedPages.capital.at)}`
                                    : 'Not changed this quarter'
                            }}</span>
                        </div>
                        <p class="hx-p mt-2">
                            {{ leverText.pages.capital?.intro }}
                        </p>
                        <p
                            class="mt-2 rounded-md px-3 py-2 text-[15px]"
                            style="background: var(--hx-teal-wash)"
                        >
                            This quarter Ingrid can take up to
                            <strong>${{ fmt(desk.capital.envelope) }}M</strong>
                            of new projects to the board, and Halden's cost of
                            capital is
                            <strong
                                >{{ fmt(desk.capital.rate * 100, 1) }}%</strong
                            >.
                        </p>
                        <div
                            v-for="pr in desk.capital.projects"
                            :key="pr.key"
                            class="hx-lever"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[16px] font-semibold">{{
                                    pr.label
                                }}</span>
                                <span class="hx-mono text-[14px]"
                                    >${{ fmt(pr.outlay) }}M up front</span
                                >
                                <span
                                    v-if="lever(`proj_${pr.key}`).isNew"
                                    class="hx-badge hx-badge-new"
                                    >NEW</span
                                >
                            </div>
                            <div class="hx-hint mt-1 mb-3">
                                {{ leverText.help[`proj_${pr.key}`] }}
                            </div>
                            <div
                                v-if="
                                    desk.capital.committedBefore.includes(
                                        pr.key,
                                    )
                                "
                                class="font-semibold"
                                style="color: var(--hx-teal)"
                            >
                                Committed. It's under way.
                            </div>
                            <div v-else class="flex flex-wrap gap-2.5">
                                <button
                                    v-for="(label, choice) in leverText.choices
                                        .project"
                                    :key="choice"
                                    type="button"
                                    class="hx-opt"
                                    :aria-pressed="
                                        draft[`proj_${pr.key}`] === choice
                                    "
                                    :disabled="!editable"
                                    @click="
                                        draft[`proj_${pr.key}`] = String(choice)
                                    "
                                >
                                    {{ label }}
                                </button>
                            </div>
                        </div>
                        <div v-if="errors.capital" class="hx-error mt-2">
                            {{ errors.capital }}
                        </div>
                        <div class="hx-desk">
                            <div>
                                <span class="hx-eyebrow">What this means</span>
                                <div class="hx-mono mt-1 text-[14px]">
                                    ${{ fmt(newOutlay) }}M of new projects this
                                    quarter, out of ${{
                                        fmt(desk.capital.envelope)
                                    }}M.
                                </div>
                            </div>
                            <button
                                v-if="editable"
                                type="button"
                                class="hx-btn hx-btn-primary"
                                :disabled="saving === 'capital'"
                                @click="savePage('capital')"
                            >
                                Save big projects
                            </button>
                        </div>
                        <div class="hx-hint mt-2">
                            {{ savedNote.capital }}
                        </div>
                    </div>
                </template>

                <!-- Memo -->
                <template v-if="section === 'memo'">
                    <div class="hx-card">
                        <h1 class="hx-h1">Your memo · {{ quarter.label }}</h1>
                        <div class="hx-hint mb-3">
                            Half a page, about 250 words, answering this
                            quarter's big question<template v-if="content"
                                >:
                                {{
                                    content.briefing.question
                                        .charAt(0)
                                        .toLowerCase() +
                                    content.briefing.question.slice(1)
                                }}</template
                            >
                            Write it in three parts: what you recommend, why,
                            and what could go wrong (and what would change your
                            mind).
                        </div>
                        <label class="hx-sr" for="memo">Memo text</label>
                        <textarea
                            id="memo"
                            v-model="memoText"
                            :disabled="!editable"
                            class="hx-serif w-full rounded-lg p-3.5 text-[15px] leading-relaxed"
                            style="
                                min-height: 300px;
                                border: 1px solid #b9c1bc;
                                background: #fffef7;
                                color: var(--hx-ink);
                                resize: vertical;
                            "
                            placeholder="What we recommend:&#10;&#10;Why:&#10;&#10;What could go wrong, and what would change our mind:"
                        ></textarea>
                        <div v-if="errors.memo" class="hx-error">
                            {{ errors.memo }}
                        </div>
                        <div
                            class="mt-2.5 flex flex-wrap items-center justify-between gap-2.5"
                        >
                            <div
                                class="hx-mono text-[13px]"
                                :style="{
                                    color:
                                        memoWords > 320
                                            ? 'var(--hx-amber)'
                                            : 'var(--hx-muted)',
                                }"
                            >
                                {{ memoWords }} words · aim for about 250
                            </div>
                            <button
                                v-if="editable"
                                type="button"
                                class="hx-btn hx-btn-primary"
                                :disabled="saving === 'memo'"
                                @click="saveMemo"
                            >
                                Save draft
                            </button>
                        </div>
                        <div class="hx-hint mt-2">
                            {{
                                savedNote.memo ||
                                (memo.savedAt
                                    ? `Last saved ${timeOf(memo.savedAt)}.`
                                    : '')
                            }}
                        </div>
                    </div>
                </template>

                <!-- Check and submit -->
                <template v-if="section === 'check'">
                    <div class="hx-card">
                        <h1 class="hx-h1">
                            Are you ready for the quarter to run?
                        </h1>
                        <div class="hx-hint mb-3.5">
                            Whatever's saved at the deadline is what happens.
                            Anything you haven't changed stays the same as last
                            quarter.
                        </div>
                        <div class="overflow-x-auto">
                            <table class="hx-tbl">
                                <thead>
                                    <tr>
                                        <th>Page</th>
                                        <th>Status</th>
                                        <th>Last saved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in checkRows" :key="r.page">
                                        <td class="font-medium">
                                            {{ r.title }}
                                        </td>
                                        <td
                                            :style="
                                                r.changed
                                                    ? 'color: var(--hx-teal); font-weight: 600'
                                                    : 'color: var(--hx-amber-text); font-weight: 600'
                                            "
                                        >
                                            {{
                                                r.changed
                                                    ? 'Changed this quarter'
                                                    : "Not changed: last quarter's numbers will be used"
                                            }}
                                        </td>
                                        <td class="hx-hint">{{ r.who }}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-medium">Your memo</td>
                                        <td
                                            :style="
                                                memo.text
                                                    ? 'color: var(--hx-teal); font-weight: 600'
                                                    : 'color: var(--hx-amber-text); font-weight: 600'
                                            "
                                        >
                                            {{
                                                memo.text
                                                    ? `Draft, ${memo.text.trim().split(/\s+/).length} words`
                                                    : 'Not started'
                                            }}
                                        </td>
                                        <td class="hx-hint">
                                            {{
                                                memo.savedAt
                                                    ? timeOf(memo.savedAt)
                                                    : '—'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            class="mt-4 rounded-lg px-3.5 py-3"
                            style="background: var(--hx-soft)"
                        >
                            <div class="hx-eyebrow mb-1">
                                What your team is signing up for
                            </div>
                            <div class="hx-mono text-[14px] leading-relaxed">
                                {{ commitText }}
                            </div>
                        </div>
                        <div
                            v-if="isOpenQuarter"
                            class="mt-4 flex flex-wrap items-center gap-3.5"
                        >
                            <button
                                v-if="!readOnly"
                                type="button"
                                class="hx-btn"
                                :class="
                                    ready ? 'hx-btn-outline' : 'hx-btn-amber'
                                "
                                :disabled="me?.seat !== 'evp'"
                                @click="toggleReady"
                            >
                                {{
                                    ready
                                        ? "We're done · click to undo"
                                        : "Tell your instructor we're done"
                                }}
                            </button>
                            <span class="hx-hint ml-2"
                                >Only the EVP can click this. It tells your
                                instructor you're done. You can still make
                                changes until the deadline.</span
                            >
                            <div v-if="errors.ready" class="hx-error">
                                {{ errors.ready }}
                            </div>
                        </div>
                        <div
                            v-if="deadlinePassed && isOpenQuarter"
                            class="hx-hint mt-2"
                        >
                            The deadline has passed. Your instructor will run
                            the quarter soon.
                        </div>
                    </div>
                </template>

                <!-- Results not ready -->
                <div v-if="resultsLocked" class="hx-card p-10 text-center">
                    <h1 class="hx-h1">
                        Results show up after the quarter closes
                    </h1>
                    <p class="hx-p">
                        {{ quarter.label }} closes at the deadline. Your results
                        show up once your instructor publishes them.
                    </p>
                </div>

                <!-- Results -->
                <template v-if="showResults && results">
                    <div
                        v-if="section === 'story' && feedback"
                        class="hx-card"
                        style="
                            border-color: var(--hx-teal);
                            background: var(--hx-teal-wash);
                        "
                    >
                        <div class="hx-eyebrow mb-1.5">
                            {{ feedback.title }}
                        </div>
                        <p class="hx-p whitespace-pre-line">
                            {{ feedback.text }}
                        </p>
                    </div>
                    <div v-if="section === 'story'" class="hx-card">
                        <div class="hx-eyebrow mb-2">
                            {{ quarter.label }} · What happened
                        </div>
                        <h1 class="hx-h1 mb-3.5">{{ results.story.title }}</h1>
                        <p
                            v-for="(p, i) in results.story.paragraphs"
                            :key="i"
                            class="hx-p hx-serif text-[17px]"
                        >
                            {{ p }}
                        </p>
                        <div class="mt-3.5">
                            <button
                                type="button"
                                class="hx-btn hx-btn-primary"
                                @click="go('pnl')"
                            >
                                See the numbers
                            </button>
                        </div>
                    </div>

                    <template v-if="section === 'pnl'">
                        <div class="hx-card">
                            <h1 class="hx-h1">
                                Profit and loss · {{ quarter.label }}
                            </h1>
                            <div class="hx-hint mb-3">
                                Profit before interest, tax and depreciation
                                (EBITDA), in $ millions.
                            </div>
                            <div class="overflow-x-auto">
                                <table class="hx-tbl">
                                    <thead>
                                        <tr>
                                            <th>Part of Halden</th>
                                            <th>Last quarter</th>
                                            <th>This quarter</th>
                                            <th>Change</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="r in results.pnl"
                                            :key="r.name"
                                        >
                                            <td
                                                :class="
                                                    r.total
                                                        ? 'font-bold'
                                                        : 'font-medium'
                                                "
                                            >
                                                {{ r.name }}
                                            </td>
                                            <td
                                                class="hx-mono"
                                                style="color: var(--hx-muted)"
                                            >
                                                {{ money(r.last) }}
                                            </td>
                                            <td
                                                class="hx-mono"
                                                :class="
                                                    r.total ? 'font-bold' : ''
                                                "
                                            >
                                                {{ money(r.now) }}
                                            </td>
                                            <td
                                                class="hx-mono"
                                                :style="{
                                                    color:
                                                        r.now - r.last >= 0
                                                            ? 'var(--hx-teal)'
                                                            : 'var(--hx-amber)',
                                                }"
                                            >
                                                {{
                                                    signedMoney(r.now - r.last)
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <h2 class="hx-h2 mt-5">
                                What's behind the numbers
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="hx-tbl">
                                    <thead>
                                        <tr>
                                            <th>What</th>
                                            <th>Amount</th>
                                            <th>Why</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="n in results.named"
                                            :key="n.name"
                                        >
                                            <td class="font-medium">
                                                {{ n.name }}
                                            </td>
                                            <td class="hx-mono">
                                                {{ money(n.amount) }}
                                            </td>
                                            <td class="hx-hint">{{ n.why }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="hx-card">
                            <h2 class="hx-h2">
                                Why profit went from
                                {{ money(results.bridge.previous) }} to
                                {{ money(results.bridge.now) }}
                            </h2>
                            <div class="mt-1.5 flex flex-col gap-2.5">
                                <div
                                    v-for="b in results.bridge.parts"
                                    :key="b.name"
                                    class="grid grid-cols-[minmax(0,220px)_minmax(0,1fr)_90px] items-center gap-3"
                                >
                                    <div class="text-[14px]">
                                        <div class="font-medium">
                                            {{ b.name }}
                                        </div>
                                        <div class="hx-hint">{{ b.note }}</div>
                                    </div>
                                    <div
                                        class="relative h-[22px] rounded"
                                        style="background: var(--hx-soft)"
                                    >
                                        <div
                                            class="absolute top-[3px] bottom-[3px] rounded-sm"
                                            :style="{
                                                left:
                                                    b.value >= 0
                                                        ? '50%'
                                                        : `${50 - (Math.abs(b.value) / bridgeScale) * 50}%`,
                                                width: `${(Math.abs(b.value) / bridgeScale) * 50}%`,
                                                background:
                                                    b.value >= 0
                                                        ? 'var(--hx-teal)'
                                                        : 'var(--hx-amber)',
                                            }"
                                        ></div>
                                        <div
                                            class="absolute top-[-2px] bottom-[-2px] left-1/2 w-px"
                                            style="background: #9aa3a0"
                                        ></div>
                                    </div>
                                    <div
                                        class="hx-mono text-right text-[14px]"
                                        :style="{
                                            color:
                                                b.value >= 0
                                                    ? 'var(--hx-teal)'
                                                    : 'var(--hx-amber)',
                                        }"
                                    >
                                        {{ signedMoney(b.value) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="hx-card">
                            <h2 class="hx-h2">Cash</h2>
                            <div class="hx-p m-0">
                                Free cash flow
                                {{ money(results.money.fcf) }} after
                                {{ money(results.money.tax) }} of tax and
                                {{ money(results.money.capex) }} of capital
                                spending. Net debt at the end of the quarter:
                                {{ money(results.money.netDebt) }}.
                            </div>
                        </div>
                    </template>

                    <div v-if="section === 'score'" class="hx-card">
                        <div class="mb-4 flex flex-wrap items-end gap-7">
                            <div>
                                <div class="hx-eyebrow">Your score</div>
                                <div class="hx-serif text-[48px] leading-none">
                                    {{ fmt(results.score, 1) }}
                                    <span
                                        v-if="results.scoreLast !== null"
                                        class="text-[18px]"
                                        :style="{
                                            color:
                                                results.score >=
                                                results.scoreLast
                                                    ? 'var(--hx-teal)'
                                                    : 'var(--hx-amber)',
                                        }"
                                    >
                                        {{
                                            results.score >= results.scoreLast
                                                ? '▲'
                                                : '▼'
                                        }}
                                        from {{ fmt(results.scoreLast, 1) }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <div class="hx-eyebrow">Your rank in class</div>
                                <div class="hx-serif text-[48px] leading-none">
                                    {{ results.rank }}
                                    <span
                                        class="text-[18px]"
                                        style="color: var(--hx-muted)"
                                        >of {{ props.section.teamCount
                                        }}<template v-if="results.rankLast">
                                            · was
                                            {{ results.rankLast }}</template
                                        ></span
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="hx-tbl">
                                <thead>
                                    <tr>
                                        <th>What we measure</th>
                                        <th>Counts for</th>
                                        <th>Last quarter</th>
                                        <th>This quarter</th>
                                        <th>What it means</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="k in results.kpis" :key="k.name">
                                        <td class="font-medium">
                                            {{ k.name }}
                                        </td>
                                        <td class="hx-mono">{{ k.weight }}</td>
                                        <td
                                            class="hx-mono"
                                            style="color: var(--hx-muted)"
                                        >
                                            {{ kpiValue(k.unit, k.last) }}
                                        </td>
                                        <td class="hx-mono font-semibold">
                                            {{ kpiValue(k.unit, k.now) }}
                                        </td>
                                        <td class="hx-hint">{{ k.def }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="hx-hint mt-3">
                            You'll see how each of these compares with the other
                            teams at the midterm (quarter 7) and again at the
                            end.
                        </div>
                    </div>

                    <div v-if="section === 'earlier'" class="hx-card">
                        <h1 class="hx-h1">What your earlier choices did</h1>
                        <div class="hx-hint mb-3.5">
                            Things you decided earlier that showed up this
                            quarter.
                        </div>
                        <div class="flex flex-col gap-3">
                            <div
                                v-for="(e, i) in results.earlier"
                                :key="i"
                                class="rounded-lg px-4 py-3.5"
                                style="border: 1px solid var(--hx-line)"
                            >
                                <div class="hx-eyebrow mb-1">{{ e.when }}</div>
                                <p class="hx-p m-0">{{ e.text }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="section === 'news'" class="hx-card">
                        <h1 class="hx-h1">Industry news</h1>
                        <div class="hx-hint mb-3.5">
                            What happened across the industry this quarter,
                            based on what every team in your class did. No team
                            is named.
                        </div>
                        <div class="flex flex-col gap-3">
                            <div
                                v-for="n in results.news"
                                :key="n.title"
                                class="rounded-lg px-4 py-3.5"
                                style="background: var(--hx-soft)"
                            >
                                <div class="mb-1 font-semibold">
                                    {{ n.title }}
                                </div>
                                <p class="hx-p m-0">{{ n.body }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="section === 'people'" class="hx-card">
                        <h1 class="hx-h1">Where you stand with people</h1>
                        <div class="hx-hint mb-3.5">
                            How the people you work with feel about you right
                            now. These aren't scores.
                        </div>
                        <div class="overflow-x-auto">
                            <table class="hx-tbl">
                                <thead>
                                    <tr>
                                        <th>Who</th>
                                        <th>Last quarter</th>
                                        <th>Now</th>
                                        <th>Why</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="r in results.relations"
                                        :key="r.who"
                                    >
                                        <td class="font-medium">{{ r.who }}</td>
                                        <td style="color: var(--hx-muted)">
                                            {{ r.was }}
                                        </td>
                                        <td class="font-semibold">
                                            {{ r.now }}
                                        </td>
                                        <td class="hx-hint">{{ r.why }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </main>
        </div>
    </div>
</template>
