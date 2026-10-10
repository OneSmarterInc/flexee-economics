<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import ClassSwitcher from '@/components/halden/ClassSwitcher.vue';

interface Row {
    id: number;
    number: number;
    label: string;
    week: number;
    status: string;
    deadline: string | null;
    editable: boolean;
    carries: boolean;
}

const props = defineProps<{
    section: { id: number; name: string; course: string; weeks: number };
    classes: { id: number; name: string; course: string }[];
    rows: Row[];
    done: string | null;
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const busy = ref(false);
const q = `?section=${props.section.id}`;

const deadlines = reactive<Record<number, string>>(
    Object.fromEntries(
        props.rows
            .filter((r) => r.deadline !== null)
            .map((r) => [r.id, r.deadline as string]),
    ),
);
const shiftFollowing = ref(true);

const changed = computed(() =>
    props.rows.filter(
        (r) => r.deadline !== null && deadlines[r.id] !== r.deadline,
    ),
);

const doneText = computed(() => {
    if (!props.done?.startsWith('changed:')) {
        return null;
    }
    const n = Number(props.done.slice(8));
    return n === 0
        ? 'Nothing changed.'
        : `${n} deadline${n === 1 ? '' : 's'} changed.`;
});

function statusText(s: string): string {
    return (
        (
            {
                upcoming: 'not open yet',
                open: 'open',
                closed: 'run, results not shown',
                published: 'results shown',
            } as Record<string, string>
        )[s] ?? s
    );
}

function pretty(v: string | null): string {
    if (!v) {
        return '';
    }
    const d = new Date(v);
    return d.toLocaleString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function save() {
    busy.value = true;
    router.post(
        '/faculty/schedule' + q,
        { deadlines, shift_following: shiftFollowing.value },
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}
</script>

<template>
    <Head :title="`${section.name} · Schedule`" />
    <div class="hx min-h-screen">
        <header
            class="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5 md:px-7"
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
                    path="/faculty/schedule"
                />
                <span class="hx-hint" style="color: #b8c4c9">Schedule</span>
            </div>
            <div class="flex gap-4 text-[13px]">
                <a :href="`/faculty${q}`" style="color: var(--hx-mint)"
                    >Faculty board</a
                >
                <a href="/settings/profile" style="color: var(--hx-mint)"
                    >Settings</a
                >
            </div>
        </header>

        <main class="mx-auto max-w-[900px] px-4 py-7 md:px-5">
            <p
                v-if="doneText"
                class="mb-4 rounded-md px-4 py-3 text-[14px]"
                style="background: var(--hx-teal-wash)"
            >
                {{ doneText }}
            </p>
            <p v-if="errors.schedule" class="hx-error mb-4">
                {{ errors.schedule }}
            </p>

            <h1 class="hx-h1">Deadlines</h1>
            <p class="hx-hint mt-1">
                Times are Eastern. A quarter closes and runs at its deadline. A
                deadline can change until its quarter has run; "Extend one day"
                on the board does the same for the current one.
                <span v-if="section.weeks < 14"
                    >In a 7-week class each deadline covers the week's two
                    quarters.</span
                >
            </p>

            <label class="mt-4 flex items-center gap-2 text-[14px]">
                <input v-model="shiftFollowing" type="checkbox" />
                <span
                    >When I move a deadline, move every later one by the same
                    amount</span
                >
            </label>

            <div class="hx-card mt-4 overflow-x-auto p-0">
                <table class="hx-tbl w-full">
                    <thead>
                        <tr>
                            <th scope="col">
                                {{ section.weeks < 14 ? 'Week' : 'Quarter' }}
                            </th>
                            <th scope="col">Status</th>
                            <th scope="col">Deadline (Eastern)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="r in rows" :key="r.id">
                            <tr v-if="!r.carries">
                                <th scope="row" class="font-semibold">
                                    <template v-if="section.weeks < 14"
                                        >Week {{ r.week }}
                                        <span
                                            class="hx-hint block font-normal"
                                            >{{ r.label }}</span
                                        ></template
                                    >
                                    <template v-else
                                        >Q{{ r.number }} ·
                                        {{ r.label }}</template
                                    >
                                </th>
                                <td>{{ statusText(r.status) }}</td>
                                <td>
                                    <input
                                        v-if="r.editable"
                                        v-model="deadlines[r.id]"
                                        type="datetime-local"
                                        class="hx-in"
                                        style="width: 230px"
                                    />
                                    <span v-else>{{ pretty(r.deadline) }}</span>
                                    <span
                                        v-if="
                                            r.editable &&
                                            deadlines[r.id] !== r.deadline
                                        "
                                        class="hx-hint block"
                                        >was {{ pretty(r.deadline) }}</span
                                    >
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="hx-btn hx-btn-primary"
                    :disabled="busy || changed.length === 0"
                    @click="save"
                >
                    Save the schedule
                </button>
                <span v-if="changed.length" class="hx-hint"
                    >{{ changed.length }} changed<span v-if="shiftFollowing"
                        >, and the ones after each will move with it</span
                    ></span
                >
            </div>
        </main>
    </div>
</template>
