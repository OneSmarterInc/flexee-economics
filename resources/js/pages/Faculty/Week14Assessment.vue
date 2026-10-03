<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

type Dimension = {
    label: string;
    description?: string;
};

type AssessmentDimension = {
    key: string;
    label: string;
    faculty_evaluation?: string | null;
    comments?: string | null;
};

const props = defineProps<{
    week: {
        title: string;
        number: number;
        status: string;
    };
    team: {
        name: string;
    };
    submission: {
        status: string;
        final_synthesis_memo: string;
        artifact_references: Array<{
            type: string;
            label?: string | null;
            reference: string;
        }>;
        submitted_at?: string | null;
    } | null;
    assessment: {
        status: string;
        reasoning_outcome_tier?: string | null;
        dimensions?: AssessmentDimension[];
        faculty_private_notes?: string | null;
        feedback?: {
            body?: string | null;
            is_published?: boolean;
            published_at?: string | null;
        };
    } | null;
    rubric: Record<string, Dimension>;
    tiers: string[];
    routes: {
        save: string;
        publish: string | null;
    };
}>();

const dimensionState = reactive(
    Object.fromEntries(
        Object.entries(props.rubric).map(([key]) => {
            const existing = props.assessment?.dimensions?.find(
                (dimension) => dimension.key === key,
            );

            return [
                key,
                {
                    faculty_evaluation:
                        existing?.faculty_evaluation?.toString() ?? '',
                    comments: existing?.comments?.toString() ?? '',
                },
            ];
        }),
    ) as Record<string, { faculty_evaluation: string; comments: string }>,
);
const reasoningOutcomeTier = ref(
    props.assessment?.reasoning_outcome_tier ?? '',
);
const facultyPrivateNotes = ref(props.assessment?.faculty_private_notes ?? '');
const feedbackBody = ref(props.assessment?.feedback?.body ?? '');
const saving = ref(false);
const publishing = ref(false);
const error = ref<string | null>(null);

const isPublished = computed(
    () => props.assessment?.feedback?.is_published === true,
);
const canPublish = computed(
    () =>
        props.assessment?.status === 'completed' &&
        props.routes.publish !== null &&
        !isPublished.value,
);

function csrfToken(): string | null {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
}

async function postJson(url: string, payload: Record<string, unknown>) {
    const token = csrfToken();

    return await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(payload),
    });
}

async function saveAssessment(complete: boolean) {
    saving.value = true;
    error.value = null;

    try {
        const response = await postJson(props.routes.save, {
            dimensions: dimensionState,
            reasoning_outcome_tier: reasoningOutcomeTier.value || null,
            faculty_private_notes: facultyPrivateNotes.value || null,
            feedback_body: feedbackBody.value || null,
            complete,
        });

        if (!response.ok) {
            error.value = 'Assessment could not be saved.';
            return;
        }

        router.reload();
    } finally {
        saving.value = false;
    }
}

async function publishFeedback() {
    if (!props.routes.publish) {
        return;
    }

    publishing.value = true;
    error.value = null;

    try {
        const response = await postJson(props.routes.publish, {});

        if (!response.ok) {
            error.value = 'Feedback could not be published.';
            return;
        }

        router.reload();
    } finally {
        publishing.value = false;
    }
}
</script>

<template>
    <Head :title="`Week ${week.number} assessment`" />

    <main class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <section class="rounded-lg border p-5">
            <p class="text-muted-foreground text-sm">
                Week {{ week.number }} · {{ week.status }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold">
                {{ week.title }} assessment
            </h1>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ team.name }}
            </p>
        </section>

        <section class="rounded-lg border p-5">
            <h2 class="font-medium">Board submission</h2>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ submission?.status ?? 'not_submitted' }}
            </p>
            <p class="mt-4 text-sm whitespace-pre-wrap">
                {{ submission?.final_synthesis_memo ?? 'No submission yet.' }}
            </p>
            <div
                v-if="submission?.artifact_references?.length"
                class="mt-4 grid gap-3"
            >
                <article
                    v-for="artifact in submission.artifact_references"
                    :key="`${artifact.type}:${artifact.reference}`"
                    class="rounded-md border p-3 text-sm"
                >
                    <h3 class="font-medium">
                        {{ artifact.label ?? artifact.type }}
                    </h3>
                    <p class="text-muted-foreground mt-1 break-all">
                        {{ artifact.reference }}
                    </p>
                </article>
            </div>
        </section>

        <section class="rounded-lg border p-5">
            <h2 class="font-medium">Rubric assessment</h2>
            <div class="mt-4 grid gap-4">
                <article
                    v-for="(dimension, key) in rubric"
                    :key="key"
                    class="grid gap-3 rounded-md border p-3"
                >
                    <div>
                        <h3 class="font-medium">{{ dimension.label }}</h3>
                        <p class="text-muted-foreground text-sm">
                            {{ dimension.description }}
                        </p>
                    </div>
                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Faculty evaluation</span>
                        <input
                            v-model="dimensionState[key].faculty_evaluation"
                            class="bg-background rounded-md border p-2"
                        />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-medium">Comments</span>
                        <textarea
                            v-model="dimensionState[key].comments"
                            class="bg-background min-h-24 rounded-md border p-2"
                        />
                    </label>
                </article>
            </div>
        </section>

        <section class="rounded-lg border p-5">
            <h2 class="font-medium">Feedback</h2>
            <div class="mt-4 grid gap-3">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Reasoning/outcome tier</span>
                    <select
                        v-model="reasoningOutcomeTier"
                        class="bg-background rounded-md border p-2"
                    >
                        <option value="">Select</option>
                        <option v-for="tier in tiers" :key="tier" :value="tier">
                            {{ tier }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Private faculty notes</span>
                    <textarea
                        v-model="facultyPrivateNotes"
                        class="bg-background min-h-24 rounded-md border p-2"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Student feedback</span>
                    <textarea
                        v-model="feedbackBody"
                        class="bg-background min-h-32 rounded-md border p-2"
                    />
                </label>
            </div>

            <p v-if="error" class="text-destructive mt-3 text-sm">
                {{ error }}
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    class="rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                    :disabled="saving"
                    @click="saveAssessment(false)"
                >
                    Save Draft
                </button>
                <button
                    class="bg-primary text-primary-foreground rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                    :disabled="saving"
                    @click="saveAssessment(true)"
                >
                    Complete Assessment
                </button>
                <button
                    class="rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                    :disabled="publishing || !canPublish"
                    @click="publishFeedback"
                >
                    Publish Feedback
                </button>
            </div>

            <p v-if="isPublished" class="text-muted-foreground mt-3 text-sm">
                Feedback published.
            </p>
        </section>
    </main>
</template>
