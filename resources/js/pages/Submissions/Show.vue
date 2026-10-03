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

type CapitalAllocationFormPayload = {
    selected_project_keys: string[];
    capital_allocation?: string;
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

type ContentPackage = {
    status: string;
    version?: string | null;
    package_type: string;
    validation_status?: string | null;
    message?: string | null;
};

type ContentArtifact = {
    key: string;
    label: string;
    type: string;
    visibility?: string | null;
    version?: string | null;
    reference: string;
};

type CapitalAllocationProject = {
    key: string;
    name: string;
    category: string;
    risk_class: string;
    cash_flow_reference: string;
    metadata: Record<string, unknown>;
};

type CapitalAllocationWorkspace = {
    status: string;
    selected_project_keys: string[];
    context: {
        status: string;
        classification?: string | null;
        discount_rate_percent?: string | null;
        capital_envelope_musd?: string | null;
        reason?: string | null;
    };
    projects: CapitalAllocationProject[];
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
    contentPackage: ContentPackage;
    artifacts: ContentArtifact[];
    team: {
        name: string;
    };
    decisionDefinition: DecisionDefinition | null;
    memoDefinition: MemoDefinition | null;
    status: {
        decision_status: string;
        memo_status: string;
        capital_allocation_status?: string;
        complete: boolean;
        ready_for_evaluation: boolean;
        resolution_status: string;
        resolved_at?: string | null;
    };
    capitalAllocation: CapitalAllocationWorkspace | null;
    routes: {
        decisionDraft: string;
        decisionSubmit: string;
        capitalAllocationSubmit: string;
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

const capitalForm = useForm<CapitalAllocationFormPayload>({
    selected_project_keys: [
        ...(props.capitalAllocation?.selected_project_keys ?? []),
    ],
});

const disabled = computed(() => !props.week.can_write);
const submitted = computed(
    () =>
        (props.decisionDefinition?.status === 'submitted' ||
            props.decisionDefinition === null) &&
        (props.memoDefinition?.status === 'submitted' ||
            props.memoDefinition === null) &&
        (props.capitalAllocation === null ||
            props.capitalAllocation.status === 'submitted'),
);
const capitalSubmitted = computed(
    () => props.capitalAllocation?.status === 'submitted',
);
const capitalContextAvailable = computed(
    () => props.capitalAllocation?.context.status === 'available',
);
const formattedClosesAt = computed(() =>
    props.week.closes_at
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(props.week.closes_at))
        : null,
);
const packageLabel = computed(() =>
    props.contentPackage.status === 'active'
        ? `Active ${props.contentPackage.version ?? ''}`.trim()
        : 'Unavailable',
);

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
                    'contentPackage',
                    'artifacts',
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
                    'contentPackage',
                    'artifacts',
                ],
            }),
    });
}

function toggleProject(key: string) {
    if (capitalSubmitted.value || disabled.value) {
        return;
    }

    if (capitalForm.selected_project_keys.includes(key)) {
        capitalForm.selected_project_keys =
            capitalForm.selected_project_keys.filter(
                (selectedKey) => selectedKey !== key,
            );

        return;
    }

    capitalForm.selected_project_keys = [
        ...capitalForm.selected_project_keys,
        key,
    ];
}

function postCapitalAllocation() {
    capitalForm.post(props.routes.capitalAllocationSubmit, {
        preserveScroll: true,
        onSuccess: () =>
            router.reload({
                only: [
                    'decisionDefinition',
                    'memoDefinition',
                    'capitalAllocation',
                    'status',
                    'week',
                    'contentPackage',
                    'artifacts',
                ],
            }),
    });
}
</script>

<template>
    <Head :title="`Week ${week.number} submission`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <section class="rounded-lg border p-5">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <p class="text-muted-foreground text-sm">
                        {{ week.course }} - {{ week.section }} - {{ team.name }}
                    </p>
                    <h1 class="mt-1 text-2xl font-semibold">
                        Week {{ week.number }}: {{ week.title }}
                    </h1>
                </div>
                <div class="grid gap-2 text-sm sm:grid-cols-2 lg:min-w-96">
                    <div class="rounded-md border p-3">
                        <p class="text-muted-foreground text-xs">Week</p>
                        <p class="font-medium">{{ week.status }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-muted-foreground text-xs">Deadline</p>
                        <p class="font-medium">
                            {{ formattedClosesAt ?? 'Not set' }}
                        </p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-muted-foreground text-xs">Package</p>
                        <p class="font-medium">{{ packageLabel }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-muted-foreground text-xs">Workspace</p>
                        <p class="font-medium">
                            {{ submitted ? 'Submitted' : 'Draft' }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <SubmissionStatus :status="status" />

        <section class="rounded-lg border p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-medium">Content</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ contentPackage.package_type }}
                    </p>
                </div>
                <p class="text-muted-foreground text-sm">
                    {{
                        contentPackage.validation_status ??
                        contentPackage.status
                    }}
                </p>
            </div>

            <div v-if="artifacts.length" class="mt-4 grid gap-3 md:grid-cols-2">
                <article
                    v-for="artifact in artifacts"
                    :key="artifact.key"
                    class="rounded-md border p-3 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-medium">{{ artifact.label }}</h3>
                            <p class="text-muted-foreground mt-1">
                                {{ artifact.type }}
                            </p>
                        </div>
                        <p class="text-muted-foreground">
                            {{ artifact.version ?? 'current' }}
                        </p>
                    </div>
                    <p class="text-muted-foreground mt-3 text-xs break-all">
                        {{ artifact.reference }}
                    </p>
                </article>
            </div>
            <p v-else class="text-muted-foreground mt-4 text-sm">
                No student materials are available.
            </p>
        </section>

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

        <section v-if="capitalAllocation" class="rounded-lg border p-5">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <h2 class="font-medium">Capital allocation</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ capitalAllocation.status }}
                    </p>
                </div>
                <div class="grid gap-2 text-sm sm:grid-cols-3 lg:min-w-[32rem]">
                    <div class="rounded-md border p-3">
                        <p class="text-muted-foreground text-xs">
                            Classification
                        </p>
                        <p class="font-medium">
                            {{
                                capitalAllocation.context.classification ??
                                capitalAllocation.context.status
                            }}
                        </p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-muted-foreground text-xs">
                            Discount rate
                        </p>
                        <p class="font-medium">
                            {{
                                capitalAllocation.context
                                    .discount_rate_percent ?? 'Pending'
                            }}
                        </p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-muted-foreground text-xs">
                            Capital envelope
                        </p>
                        <p class="font-medium">
                            {{
                                capitalAllocation.context
                                    .capital_envelope_musd ?? 'Pending'
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <p
                v-if="!capitalContextAvailable"
                class="border-destructive/40 text-destructive mt-4 rounded-md border p-3 text-sm"
            >
                {{
                    capitalAllocation.context.reason ??
                    'Capital allocation context is not available yet.'
                }}
            </p>

            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                <button
                    v-for="project in capitalAllocation.projects"
                    :key="project.key"
                    type="button"
                    class="bg-background hover:bg-accent rounded-md border p-4 text-left text-sm transition disabled:opacity-60"
                    :class="{
                        'border-primary ring-primary/20 ring-2':
                            capitalForm.selected_project_keys.includes(
                                project.key,
                            ),
                    }"
                    :disabled="disabled || capitalSubmitted"
                    @click="toggleProject(project.key)"
                >
                    <div class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            class="mt-1"
                            :checked="
                                capitalForm.selected_project_keys.includes(
                                    project.key,
                                )
                            "
                            :disabled="disabled || capitalSubmitted"
                            @click.stop="toggleProject(project.key)"
                        />
                        <div class="min-w-0">
                            <h3 class="font-medium">{{ project.name }}</h3>
                            <p class="text-muted-foreground mt-1">
                                {{ project.category }} /
                                {{ project.risk_class }}
                            </p>
                            <p
                                class="text-muted-foreground mt-3 text-xs break-all"
                            >
                                {{ project.cash_flow_reference }}
                            </p>
                        </div>
                    </div>
                </button>
            </div>

            <p
                v-if="capitalForm.errors.selected_project_keys"
                class="text-destructive mt-3 text-sm"
            >
                {{ capitalForm.errors.selected_project_keys }}
            </p>
            <p
                v-if="capitalForm.errors.capital_allocation"
                class="text-destructive mt-3 text-sm"
            >
                {{ capitalForm.errors.capital_allocation }}
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    class="bg-primary text-primary-foreground rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                    :disabled="
                        disabled ||
                        capitalSubmitted ||
                        capitalForm.processing ||
                        capitalForm.selected_project_keys.length === 0
                    "
                    @click="postCapitalAllocation"
                >
                    Submit Allocation
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
