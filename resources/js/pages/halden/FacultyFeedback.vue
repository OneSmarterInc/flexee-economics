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
    inputs: { memo: string; sheet: string[]; found: string[] };
    drafts: Record<Kind, Draft | null>;
    feedback: {
        text: string;
        writingScore: number | null;
        publishedAt: string | null;
    };
}>();

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
// The score box starts empty. The proposal sits beside it and is never added on its own.
const writingScore = ref<number | null>(props.feedback.writingScore);
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
                <div class="hx-card">
                    <h2 class="hx-h2">The team's memo</h2>
                    <p v-if="inputs.memo.trim() === ''" class="hx-hint mt-2">
                        {{ text.no_memo }}
                    </p>
                    <p v-else class="hx-p mt-2 whitespace-pre-line">
                        {{ inputs.memo }}
                    </p>
                </div>
                <div class="hx-card">
                    <h2 class="hx-h2">What they set</h2>
                    <ul class="mt-2 list-disc pl-5 text-[14px] leading-relaxed">
                        <li v-for="(l, i) in inputs.sheet" :key="i">{{ l }}</li>
                    </ul>
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
                    <div class="mt-3 flex flex-wrap items-center gap-3">
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
                            >Yours to set. The proposal above is only a
                            suggestion.</span
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
