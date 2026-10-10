<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Kind = 'feedback' | 'writing' | 'mismatch';

interface Draft {
    status: 'ok' | 'dropped';
    text: string | null;
    data: {
        score?: number;
        reason?: string;
        mismatch?: boolean;
        note?: string;
    } | null;
    reason: string | null;
    at: string | null;
}

const props = defineProps<{
    section: { id: number; name: string };
    team: { id: number; name: string };
    quarter: { id: number; number: number; label: string; status: string };
    enabled: boolean;
    text: Record<string, string>;
    labels: Record<Kind, string>;
    inputs: {
        memo: string;
        sheet: string[];
        found: string[];
        record: string[];
    };
    drafts: Record<Kind, Draft | null>;
    feedback: {
        text: string;
        writingScoreAi: number | null;
        writingAdjustment: number;
        writingScore: number | null;
        publishedAt: string | null;
    };
    board: {
        defense: Record<string, string>;
        parts: { key: string; title: string; prompt: string; words: number }[];
        reasoning: string | null;
        outcomes: {
            average: number | null;
            median: number | null;
            strong: boolean | null;
        };
        suggested: string | null;
        tier: string | null;
        verdict: string | null;
        publishedAt: string | null;
        endings: { key: string; title: string }[];
        resultsPublished: boolean;
    } | null;
}>();

// The board's verdict (the last quarter): the instructor's reasoning call plus the scorecard pick the ending.
const reasoning = ref<string | null>(props.board?.reasoning ?? null);
const verdictChoice = ref<string | null>(props.board?.verdict ?? null);
const savingVerdict = ref(false);
function suggestedEnding(): string | null {
    const strong = props.board?.outcomes.strong;
    if (reasoning.value === null || strong === null || strong === undefined) {
        return null;
    }
    if (reasoning.value === 'strong') {
        return strong ? 'widen' : 'split';
    }

    return strong ? 'conditions' : 'sold';
}
function saveVerdict(publish: boolean) {
    savingVerdict.value = true;
    router.post(
        `/faculty/teams/${props.team.id}/quarters/${props.quarter.id}/verdict${qs.value}`,
        {
            reasoning: reasoning.value,
            verdict: verdictChoice.value ?? suggestedEnding(),
            publish,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                savingVerdict.value = false;
            },
        },
    );
}

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const base = computed(
    () =>
        `/faculty/teams/${props.team.id}/quarters/${props.quarter.id}/feedback`,
);
const qs = computed(() => `?section=${props.section.id}`);

const feedbackText = ref(props.feedback.text);
// With an AI score, faculty adjust it up or down from zero. Without one, they score it themselves.
const writingScore = ref<number | null>(props.feedback.writingScore);
const adjustment = ref<number>(props.feedback.writingAdjustment);
const aiScore = computed(() => props.feedback.writingScoreAi);
const finalScore = computed(() =>
    aiScore.value === null
        ? writingScore.value
        : Math.max(1, Math.min(5, aiScore.value + adjustment.value)),
);

function adjust(by: number) {
    if (aiScore.value === null) {
        return;
    }

    const next = adjustment.value + by;

    if (aiScore.value + next >= 1 && aiScore.value + next <= 5) {
        adjustment.value = next;
    }
}
const drafting = ref(false);
const saving = ref(false);
const hasDrafts = computed(() =>
    Object.values(props.drafts).some((d) => d !== null),
);

const fill = (key: string, values: Record<string, string>) =>
    Object.entries(values).reduce(
        (s, [k, v]) => s.replaceAll(`{${k}}`, v),
        props.text[key] ?? '',
    );

function draft() {
    drafting.value = true;
    router.post(
        `${base.value}/draft${qs.value}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                const d = props.drafts.feedback;

                if (
                    d &&
                    d.status === 'ok' &&
                    d.text &&
                    feedbackText.value.trim() === ''
                ) {
                    feedbackText.value = d.text;
                }
            },
            onFinish: () => (drafting.value = false),
        },
    );
}

function useDraft() {
    if (props.drafts.feedback?.text) {
        feedbackText.value = props.drafts.feedback.text;
    }
}

function save(publish: boolean) {
    if (
        publish &&
        !window.confirm(
            'Send this feedback to the team? They will see it with their results.',
        )
    ) {
        return;
    }

    saving.value = true;
    router.post(
        `${base.value}${qs.value}`,
        {
            feedback: feedbackText.value,
            writing_score: writingScore.value,
            writing_adjustment: adjustment.value,
            publish,
        },
        { preserveScroll: true, onFinish: () => (saving.value = false) },
    );
}

function when(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('en-US', {
              weekday: 'short',
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          })
        : '';
}
</script>

<template>
    <Head :title="`Feedback · ${team.name}`" />
    <div class="hx min-h-screen">
        <header
            class="flex flex-wrap items-center justify-between gap-3 px-7 py-3.5"
            style="background: var(--hx-night); color: #e6ecef"
        >
            <span
                class="hx-mono text-[13px] tracking-[0.2em]"
                style="color: var(--hx-mint)"
                >HALDEN · FACULTY</span
            >
            <a
                :href="`/faculty?section=${section.id}`"
                class="text-[13px]"
                style="color: var(--hx-mint)"
                >Back to the board</a
            >
        </header>

        <main
            class="mx-auto grid max-w-[1200px] gap-5 px-5 py-7 lg:grid-cols-[1fr_1fr]"
        >
            <section class="flex flex-col gap-4">
                <div>
                    <h1 class="hx-h1">
                        {{
                            fill('title', {
                                team: team.name,
                                quarter: quarter.label,
                            })
                        }}
                    </h1>
                    <p class="hx-hint mt-1">{{ text.intro }}</p>
                </div>
                <div v-if="board" class="hx-card">
                    <h2 class="hx-h2">The board's verdict</h2>
                    <p class="hx-hint mt-1">
                        You decide whether the reasoning was strong, from the
                        defense and the fourteen memos. The scorecard decides
                        the outcomes: this team's average score over the course,
                        against the class median. Together they pick the ending;
                        you can change it before publishing.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2.5">
                        <span class="text-[14px] font-semibold"
                            >Reasoning:</span
                        >
                        <button
                            v-for="opt in ['strong', 'weak']"
                            :key="opt"
                            type="button"
                            class="hx-opt"
                            :aria-pressed="reasoning === opt"
                            @click="reasoning = opt"
                        >
                            {{ opt === 'strong' ? 'Strong' : 'Weak' }}
                        </button>
                    </div>
                    <div class="hx-mono mt-2 text-[13px]">
                        Outcomes:
                        {{
                            board.outcomes.strong === null
                                ? 'no scores yet'
                                : `${board.outcomes.strong ? 'strong' : 'weaker'} (average ${board.outcomes.average?.toFixed(1)} against a class median of ${board.outcomes.median?.toFixed(1)})`
                        }}
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2.5">
                        <span class="text-[14px] font-semibold">Ending:</span>
                        <button
                            v-for="e in board.endings"
                            :key="e.key"
                            type="button"
                            class="hx-opt"
                            :aria-pressed="
                                (verdictChoice ?? suggestedEnding()) === e.key
                            "
                            @click="verdictChoice = e.key"
                        >
                            {{ e.title }}
                        </button>
                    </div>
                    <div v-if="suggestedEnding()" class="hx-hint mt-1">
                        The four-tier table suggests:
                        {{
                            board.endings.find(
                                (e) => e.key === suggestedEnding(),
                            )?.title
                        }}
                    </div>
                    <div v-if="errors.verdict" class="hx-error">
                        {{ errors.verdict }}
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            class="hx-btn hx-btn-outline"
                            :disabled="savingVerdict"
                            @click="saveVerdict(false)"
                        >
                            Save
                        </button>
                        <button
                            type="button"
                            class="hx-btn hx-btn-primary"
                            :disabled="savingVerdict || !board.resultsPublished"
                            @click="saveVerdict(true)"
                        >
                            Publish the verdict to the team
                        </button>
                        <span v-if="board.publishedAt" class="hx-hint"
                            >Published {{ when(board.publishedAt) }}.</span
                        >
                        <span
                            v-else-if="!board.resultsPublished"
                            class="hx-hint"
                            >Show the quarter's results first.</span
                        >
                    </div>
                </div>
                <div class="hx-card">
                    <h2 class="hx-h2">{{ text.memo_title }}</h2>
                    <p v-if="inputs.memo.trim() === ''" class="hx-hint mt-2">
                        {{ text.no_memo }}
                    </p>
                    <p v-else class="hx-p mt-2 whitespace-pre-line">
                        {{ inputs.memo }}
                    </p>
                </div>
                <div class="hx-card">
                    <h2 class="hx-h2">{{ text.sheet_title }}</h2>
                    <ul class="mt-2 list-disc pl-5 text-[14px] leading-relaxed">
                        <li v-for="(l, i) in inputs.sheet" :key="i">{{ l }}</li>
                    </ul>
                </div>
                <div v-if="inputs.record.length" class="hx-card">
                    <h2 class="hx-h2">{{ text.record_title }}</h2>
                    <p class="hx-hint mt-1">
                        Each quarter's memo goes to the drafts in full. Open a
                        quarter to read it.
                    </p>
                    <details
                        v-for="(l, i) in inputs.record"
                        :key="i"
                        class="mt-2 text-[14px] leading-relaxed"
                    >
                        <summary class="cursor-pointer font-semibold">
                            {{ l.split(' · ')[0] }}
                        </summary>
                        <p class="hx-p mt-1 whitespace-pre-line">
                            {{ l.slice(l.indexOf(' · ') + 3) }}
                        </p>
                    </details>
                </div>
                <div class="hx-card">
                    <h2 class="hx-h2">What the model found</h2>
                    <ul class="mt-2 list-disc pl-5 text-[14px] leading-relaxed">
                        <li v-for="(l, i) in inputs.found" :key="i">{{ l }}</li>
                    </ul>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <div class="hx-card">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="hx-h2">AI drafts</h2>
                        <button
                            type="button"
                            class="hx-btn hx-btn-outline"
                            :disabled="drafting || !enabled"
                            @click="draft"
                        >
                            {{
                                drafting
                                    ? 'Drafting…'
                                    : hasDrafts
                                      ? text.redraft_button
                                      : text.draft_button
                            }}
                        </button>
                    </div>
                    <p v-if="!enabled" class="hx-hint mt-2">
                        The drafting tools need an Anthropic API key on the
                        server.
                    </p>
                    <p v-if="errors.draft" class="hx-error mt-2">
                        {{ errors.draft }}
                    </p>

                    <div
                        v-for="kind in [
                            'mismatch',
                            'writing',
                            'feedback',
                        ] as Kind[]"
                        :key="kind"
                        class="mt-4 border-t pt-3"
                        style="border-color: var(--hx-line)"
                    >
                        <div class="hx-eyebrow">{{ labels[kind] }}</div>
                        <p v-if="!drafts[kind]" class="hx-hint mt-1">
                            Not drafted yet.
                        </p>
                        <p
                            v-else-if="drafts[kind]!.status === 'dropped'"
                            class="mt-1 text-[14px]"
                            style="color: var(--hx-amber-text)"
                        >
                            {{
                                fill('dropped', {
                                    reason: drafts[kind]!.reason ?? '',
                                })
                            }}
                        </p>
                        <template v-else-if="kind === 'mismatch'">
                            <p
                                class="mt-1 font-semibold"
                                :style="
                                    drafts.mismatch!.data?.mismatch
                                        ? 'color: var(--hx-amber-text)'
                                        : 'color: var(--hx-teal)'
                                "
                            >
                                {{
                                    drafts.mismatch!.data?.mismatch
                                        ? 'Possible mismatch'
                                        : 'Matches'
                                }}
                            </p>
                            <p class="text-[14px]">
                                {{ drafts.mismatch!.data?.note }}
                            </p>
                        </template>
                        <template v-else-if="kind === 'writing'">
                            <p class="mt-1">
                                <span class="font-semibold"
                                    >Proposed:
                                    {{ drafts.writing!.data?.score }} of
                                    5.</span
                                >
                                {{ drafts.writing!.data?.reason }}
                            </p>
                        </template>
                        <template v-else>
                            <p
                                class="mt-1 text-[14px] leading-relaxed whitespace-pre-line"
                            >
                                {{ drafts.feedback!.text }}
                            </p>
                            <button
                                type="button"
                                class="mt-2 text-[14px] underline"
                                style="color: var(--hx-teal)"
                                @click="useDraft"
                            >
                                Copy into the feedback box
                            </button>
                        </template>
                    </div>
                </div>

                <div class="hx-card">
                    <h2 class="hx-h2">Your feedback to the team</h2>
                    <label class="hx-sr" for="fb">Feedback</label>
                    <textarea
                        id="fb"
                        v-model="feedbackText"
                        class="hx-in hx-in-wide mt-2 w-full"
                        rows="12"
                    />
                    <p v-if="errors.feedback" class="hx-error mt-1">
                        {{ errors.feedback }}
                    </p>
                    <div
                        v-if="aiScore !== null"
                        class="mt-3 flex flex-wrap items-center gap-3"
                    >
                        <span class="text-[14px]">Writing score</span>
                        <span
                            class="hx-mono rounded px-2 py-1"
                            style="background: var(--hx-soft)"
                            >AI {{ aiScore }}</span
                        >
                        <span class="text-[14px]">Adjustment</span>
                        <button
                            type="button"
                            class="hx-btn hx-btn-outline px-3"
                            aria-label="Adjust down"
                            :disabled="aiScore + adjustment <= 1"
                            @click="adjust(-1)"
                        >
                            −
                        </button>
                        <span
                            class="hx-mono w-8 text-center"
                            aria-live="polite"
                            >{{
                                adjustment > 0 ? `+${adjustment}` : adjustment
                            }}</span
                        >
                        <button
                            type="button"
                            class="hx-btn hx-btn-outline px-3"
                            aria-label="Adjust up"
                            :disabled="aiScore + adjustment >= 5"
                            @click="adjust(1)"
                        >
                            +
                        </button>
                        <span class="text-[14px] font-semibold"
                            >= {{ finalScore }} of 5</span
                        >
                    </div>
                    <div v-else class="mt-3 flex flex-wrap items-center gap-3">
                        <label for="ws" class="text-[14px]"
                            >Writing score (1–5)</label
                        >
                        <input
                            id="ws"
                            v-model.number="writingScore"
                            type="number"
                            min="1"
                            max="5"
                            step="1"
                            class="hx-in w-20"
                        />
                        <span class="hx-hint"
                            >No AI score yet, so this one is yours to set.</span
                        >
                    </div>
                    <p v-if="errors.writing_score" class="hx-error mt-1">
                        {{ errors.writing_score }}
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="hx-btn hx-btn-outline"
                            :disabled="saving"
                            @click="save(false)"
                        >
                            Save
                        </button>
                        <button
                            type="button"
                            class="hx-btn hx-btn-primary"
                            :disabled="saving"
                            @click="save(true)"
                        >
                            {{ text.publish }}
                        </button>
                        <span v-if="feedback.publishedAt" class="hx-hint">{{
                            fill('published', {
                                when: when(feedback.publishedAt),
                            })
                        }}</span>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
