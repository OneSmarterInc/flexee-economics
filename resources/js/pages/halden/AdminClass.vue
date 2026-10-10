<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    section: {
        id: number;
        name: string;
        course: string;
        weeks: number;
        instructor: number;
        seats: number | null;
        advisorsEnabled: boolean;
        requiresPayment: boolean;
        coInstructors: { id: number; name: string; email: string }[];
        firstDeadline: string | null;
        started: boolean;
        teams: { name: string; members: number }[];
        quarters: {
            number: number;
            label: string;
            status: string;
            deadline: string | null;
        }[];
    };
    usage: {
        advisorAnswers: number;
        advisorTokens: number;
        drafts: number;
        draftTokens: number;
    };
    instructors: { id: number; name: string; email: string }[];
    done: string | null;
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const busy = ref(false);

const form = reactive({
    name: props.section.name,
    course: props.section.course,
    weeks: props.section.weeks,
    instructor: props.section.instructor,
    first_deadline: props.section.firstDeadline ?? '',
    seats: (props.section.seats ?? '') as string | number,
    advisors_enabled: props.section.advisorsEnabled,
    requires_payment: props.section.requiresPayment,
});

function save() {
    busy.value = true;
    router.post(
        `/admin/classes/${props.section.id}`,
        {
            ...form,
            seats: form.seats === '' ? null : Number(form.seats),
            first_deadline: props.section.started ? null : form.first_deadline,
        },
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}

function remove() {
    if (
        !window.confirm(
            `Delete "${props.section.name}"? Its teams and quarters go with it. This can't be undone.`,
        )
    ) {
        return;
    }
    busy.value = true;
    router.delete(`/admin/classes/${props.section.id}`, {
        onFinish: () => (busy.value = false),
    });
}

const newCo = ref<number | ''>('');

function addCo() {
    if (newCo.value === '') {
        return;
    }
    busy.value = true;
    router.post(
        `/admin/classes/${props.section.id}/co-instructors`,
        { instructor: newCo.value },
        {
            preserveScroll: true,
            onSuccess: () => (newCo.value = ''),
            onFinish: () => (busy.value = false),
        },
    );
}

function removeCo(id: number) {
    busy.value = true;
    router.post(
        `/admin/classes/${props.section.id}/co-instructors`,
        { instructor: id, remove: true },
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}

function fmt(n: number): string {
    return n.toLocaleString('en-US');
}
</script>

<template>
    <Head :title="`${props.section.name} · Admin`" />
    <div class="hx min-h-screen">
        <header
            class="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5 md:px-7"
            style="background: var(--hx-night); color: #e6ecef"
        >
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <span
                    class="hx-mono text-[13px] tracking-[0.2em]"
                    style="color: var(--hx-mint)"
                    >HALDEN · ADMIN</span
                >
                <span class="font-semibold">{{ props.section.name }}</span>
            </div>
            <div class="flex gap-4 text-[13px]">
                <a href="/admin" style="color: var(--hx-mint)">All classes</a>
                <a
                    :href="`/faculty?section=${props.section.id}`"
                    style="color: var(--hx-mint)"
                    >Faculty board</a
                >
            </div>
        </header>

        <main class="mx-auto max-w-[1100px] px-4 py-7 md:px-5">
            <p
                v-if="props.done === 'created'"
                class="mb-4 rounded-md px-4 py-3 text-[14px]"
                style="background: var(--hx-teal-wash)"
            >
                The class is set up. Add students on the
                <a
                    :href="`/faculty/roster?section=${props.section.id}`"
                    class="underline"
                    >students and teams</a
                >
                page, then open Quarter 1 from the faculty board.
            </p>
            <p
                v-else-if="props.done === 'saved'"
                class="mb-4 rounded-md px-4 py-3 text-[14px]"
                style="background: var(--hx-teal-wash)"
            >
                Saved.
            </p>

            <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <section class="hx-card">
                    <h1 class="hx-h1">Settings</h1>
                    <p v-if="props.section.started" class="hx-hint mt-1">
                        The class has started, so its length and schedule are
                        fixed. Deadlines move from the faculty board ("Extend
                        one day").
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="hx-hint">Class name</span>
                            <input
                                v-model="form.name"
                                class="hx-in hx-in-wide"
                            />
                            <span v-if="errors.name" class="hx-error">{{
                                errors.name
                            }}</span>
                        </label>
                        <label class="block">
                            <span class="hx-hint">Course name</span>
                            <input
                                v-model="form.course"
                                class="hx-in hx-in-wide"
                            />
                        </label>
                        <label class="block">
                            <span class="hx-hint">Instructor</span>
                            <select
                                v-model="form.instructor"
                                class="hx-in hx-in-wide"
                            >
                                <option
                                    v-for="i in instructors"
                                    :key="i.id"
                                    :value="i.id"
                                >
                                    {{ i.name }} ({{ i.email }})
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="hx-hint">Length</span>
                            <select
                                v-model="form.weeks"
                                class="hx-in hx-in-wide"
                                :disabled="props.section.started"
                            >
                                <option :value="14">
                                    14 weeks, one quarter a week
                                </option>
                                <option :value="7">
                                    7 weeks, two quarters a week
                                </option>
                            </select>
                            <span v-if="errors.weeks" class="hx-error">{{
                                errors.weeks
                            }}</span>
                        </label>
                        <label class="block">
                            <span class="hx-hint"
                                >First deadline (Eastern); the rest follow
                                weekly</span
                            >
                            <input
                                v-model="form.first_deadline"
                                type="datetime-local"
                                class="hx-in hx-in-wide"
                                :disabled="props.section.started"
                            />
                        </label>
                        <label class="block">
                            <span class="hx-hint"
                                >Seats (blank for no limit)</span
                            >
                            <input
                                v-model="form.seats"
                                type="number"
                                min="1"
                                max="500"
                                class="hx-in"
                            />
                        </label>
                        <label class="flex items-center gap-2 sm:col-span-2">
                            <input
                                v-model="form.advisors_enabled"
                                type="checkbox"
                            />
                            <span
                                >Advisors and meetings are switched on for this
                                class</span
                            >
                        </label>
                        <label class="flex items-center gap-2 sm:col-span-2">
                            <input
                                v-model="form.requires_payment"
                                type="checkbox"
                            />
                            <span
                                >Each student's seat must be marked paid before
                                they play (the instructor marks them on the
                                students page; no money moves through
                                Halden)</span
                            >
                        </label>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            class="hx-btn hx-btn-primary"
                            :disabled="busy || !form.name.trim()"
                            @click="save"
                        >
                            Save
                        </button>
                        <button
                            v-if="!props.section.started"
                            type="button"
                            class="hx-btn hx-btn-outline"
                            :disabled="busy"
                            @click="remove"
                        >
                            Delete the class
                        </button>
                        <span v-if="errors.delete" class="hx-error">{{
                            errors.delete
                        }}</span>
                    </div>
                </section>

                <section class="flex flex-col gap-6">
                    <div class="hx-card">
                        <h2 class="hx-h2">Co-instructors</h2>
                        <p class="hx-hint mt-1">
                            They see and run the same board as the instructor.
                        </p>
                        <ul
                            v-if="props.section.coInstructors.length"
                            class="mt-2 text-[14px] leading-relaxed"
                        >
                            <li
                                v-for="c in props.section.coInstructors"
                                :key="c.id"
                            >
                                {{ c.name }}
                                <span class="hx-hint"> · {{ c.email }}</span>
                                <button
                                    type="button"
                                    class="ml-2 text-[13px] underline"
                                    :disabled="busy"
                                    @click="removeCo(c.id)"
                                >
                                    Remove
                                </button>
                            </li>
                        </ul>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <select
                                v-model="newCo"
                                class="hx-in"
                                style="width: 100%; max-width: 260px"
                            >
                                <option value="">Add an instructor…</option>
                                <option
                                    v-for="i in instructors.filter(
                                        (x) =>
                                            x.id !== props.section.instructor &&
                                            !props.section.coInstructors.some(
                                                (c) => c.id === x.id,
                                            ),
                                    )"
                                    :key="i.id"
                                    :value="i.id"
                                >
                                    {{ i.name }} ({{ i.email }})
                                </option>
                            </select>
                            <button
                                type="button"
                                class="hx-btn hx-btn-outline"
                                :disabled="busy || newCo === ''"
                                @click="addCo"
                            >
                                Add
                            </button>
                        </div>
                        <span v-if="errors.co_instructor" class="hx-error">{{
                            errors.co_instructor
                        }}</span>
                    </div>
                    <div class="hx-card">
                        <h2 class="hx-h2">Teams</h2>
                        <p
                            v-if="props.section.teams.length === 0"
                            class="hx-hint mt-1"
                        >
                            No teams yet.
                        </p>
                        <ul v-else class="mt-2 text-[14px] leading-relaxed">
                            <li v-for="t in props.section.teams" :key="t.name">
                                {{ t.name }}
                                <span class="hx-hint"
                                    >· {{ t.members }} students</span
                                >
                            </li>
                        </ul>
                        <p class="hx-hint mt-2">
                            <a
                                :href="`/faculty/roster?section=${props.section.id}`"
                                class="underline"
                                >Students and teams</a
                            >
                            are managed from the faculty board.
                            <a
                                :href="`/faculty/results.csv?section=${props.section.id}`"
                                class="mt-1 block underline"
                                >Download the results (CSV)</a
                            >
                        </p>
                    </div>
                    <div class="hx-card">
                        <h2 class="hx-h2">AI use so far</h2>
                        <p class="mt-2 text-[14px] leading-relaxed">
                            {{ fmt(usage.advisorAnswers) }} advisor answers ({{
                                fmt(usage.advisorTokens)
                            }}
                            tokens) and {{ fmt(usage.drafts) }} faculty drafts
                            ({{ fmt(usage.draftTokens) }}
                            tokens).
                        </p>
                        <p class="hx-hint mt-1">
                            The money limit is set on the Anthropic workspace,
                            not here.
                        </p>
                    </div>
                </section>
            </div>

            <h2 class="hx-h2 mt-8">
                Schedule
                <a
                    :href="`/faculty/schedule?section=${props.section.id}`"
                    class="hx-hint ml-2 font-normal underline"
                    >Change deadlines</a
                >
            </h2>
            <div class="hx-card mt-3 overflow-x-auto p-0">
                <table class="hx-tbl w-full">
                    <thead>
                        <tr>
                            <th scope="col">Quarter</th>
                            <th scope="col">Status</th>
                            <th scope="col">Deadline (Eastern)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="q in props.section.quarters" :key="q.number">
                            <td>Q{{ q.number }} · {{ q.label }}</td>
                            <td>{{ q.status }}</td>
                            <td>
                                {{
                                    q.deadline ??
                                    (props.section.weeks < 14
                                        ? 'runs with the quarter before'
                                        : '')
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>
