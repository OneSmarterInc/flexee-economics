<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DecisionField from '@/components/submissions/DecisionField.vue';
import SubmissionStatus from '@/components/submissions/SubmissionStatus.vue';

type DecisionDefinition = {
    ulid: string;
    name: string;
    required: boolean;
    fields: Array<{
        key: string;
        label: string;
        type: string;
        required: boolean;
        help_text?: string | null;
        unit?: string | null;
        options?: Array<{ value: string; label: string } | string>;
    }>;
    answers: Record<string, DecisionAnswer>;
    status: string;
};

type DecisionAnswer = string | number | boolean | null;

type DecisionFormPayload = {
    definition_ulid: string;
    answers: Record<string, DecisionAnswer>;
};

type MemoFormPayload = {
    definition_ulid: string;
    body: string;
};

type MemoDefinition = {
    ulid: string;
    title: string;
    instructions?: string | null;
    required: boolean;
    word_limit?: number | null;
    character_limit?: number | null;
    body: string;
    status: string;
};

const props = defineProps<{
    week: {
        title: string;
        number: number;
        status: string;
        closes_at?: string | null;
        course: string;
        section: string;
        can_write: boolean;
    };
    team: {
        name: string;
    };
    decisionDefinition: DecisionDefinition | null;
    memoDefinition: MemoDefinition | null;
    status: {
        decision_status: string;
        memo_status: string;
        complete: boolean;
        ready_for_evaluation: boolean;
    };
    routes: {
        decisionDraft: string;
        decisionSubmit: string;
        memoDraft: string;
        memoSubmit: string;
    };
}>();

const decisionForm = useForm<DecisionFormPayload>({
    definition_ulid: props.decisionDefinition?.ulid ?? '',
    answers: { ...props.decisionDefinition?.answers },
});

const memoForm = useForm<MemoFormPayload>({
    definition_ulid: props.memoDefinition?.ulid ?? '',
    body: props.memoDefinition?.body ?? '',
});

const disabled = computed(() => !props.week.can_write);

function postDecision(url: string) {
    decisionForm.post(url, {
        preserveScroll: true,
        onSuccess: () =>
            router.reload({
                only: [
                    'decisionDefinition',
                    'memoDefinition',
                    'status',
                    'week',
                ],
            }),
    });
}

function postMemo(url: string) {
    memoForm.post(url, {
        preserveScroll: true,
        onSuccess: () =>
            router.reload({
                only: [
                    'decisionDefinition',
                    'memoDefinition',
                    'status',
                    'week',
                ],
            }),
    });
}
</script>

<template>
    <Head :title="`Week ${week.number} submission`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <section class="rounded-lg border p-5">
            <p class="text-muted-foreground text-sm">
                {{ week.course }} - {{ week.section }} - {{ team.name }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold">
                Week {{ week.number }}: {{ week.title }}
            </h1>
            <p class="text-muted-foreground mt-2 text-sm">
                {{ week.status }}
                <span v-if="week.closes_at">
                    - closes {{ week.closes_at }}</span
                >
            </p>
        </section>

        <SubmissionStatus :status="status" />

        <section v-if="decisionDefinition" class="rounded-lg border p-5">
            <div
                class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h2 class="font-medium">{{ decisionDefinition.name }}</h2>
                    <p class="text-muted-foreground text-sm">
                        {{ decisionDefinition.status }}
                    </p>
                </div>
            </div>

            <div class="mt-4 grid gap-3">
                <DecisionField
                    v-for="field in decisionDefinition.fields"
                    :key="field.key"
                    v-model="decisionForm.answers[field.key]"
                    :field="field"
                    :disabled="
                        disabled || decisionDefinition.status === 'submitted'
                    "
                />
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    class="rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                    :disabled="
                        disabled ||
                        decisionForm.processing ||
                        decisionDefinition.status === 'submitted'
                    "
                    @click="postDecision(routes.decisionDraft)"
                >
                    Save Draft
                </button>
                <button
                    class="bg-primary text-primary-foreground rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                    :disabled="
                        disabled ||
                        decisionForm.processing ||
                        decisionDefinition.status === 'submitted'
                    "
                    @click="postDecision(routes.decisionSubmit)"
                >
                    Submit Decisions
                </button>
            </div>
        </section>

        <section v-if="memoDefinition" class="rounded-lg border p-5">
            <h2 class="font-medium">{{ memoDefinition.title }}</h2>
            <p
                v-if="memoDefinition.instructions"
                class="text-muted-foreground mt-1 text-sm"
            >
                {{ memoDefinition.instructions }}
            </p>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ memoDefinition.status }}
            </p>

            <textarea
                v-model="memoForm.body"
                class="bg-background mt-4 min-h-40 w-full rounded-md border p-3 text-sm"
                :disabled="disabled || memoDefinition.status === 'submitted'"
            />

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    class="rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                    :disabled="
                        disabled ||
                        memoForm.processing ||
                        memoDefinition.status === 'submitted'
                    "
                    @click="postMemo(routes.memoDraft)"
                >
                    Save Memo Draft
                </button>
                <button
                    class="bg-primary text-primary-foreground rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                    :disabled="
                        disabled ||
                        memoForm.processing ||
                        memoDefinition.status === 'submitted'
                    "
                    @click="postMemo(routes.memoSubmit)"
                >
                    Submit Memo
                </button>
            </div>
        </section>
    </div>
</template>
