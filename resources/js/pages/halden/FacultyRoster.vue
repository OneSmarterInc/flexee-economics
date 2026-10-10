<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import ClassSwitcher from '@/components/halden/ClassSwitcher.vue';

interface Student {
    id: number;
    name: string;
    email: string;
    blocked: boolean;
    teamId: number | null;
    seat: string | null;
    seenOpening: boolean;
}

interface Added {
    email: string;
    name: string;
    password: string | null;
    note: string;
}

const props = defineProps<{
    section: {
        id: number;
        name: string;
        course: string;
        seats: number | null;
        joinUrl: string | null;
        started: boolean;
    };
    classes: { id: number; name: string; course: string }[];
    students: Student[];
    teams: { id: number; name: string }[];
    seats: Record<string, string>;
    added: Added[] | null;
    rejected: string[];
    newPassword: { email: string; name: string; password: string } | null;
    done: string | null;
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const busy = ref(false);
const q = `?section=${props.section.id}`;

const addForm = reactive({ list: '', team: '' as string | number });
const file = ref<File | null>(null);
const renaming = ref<number | null>(null);
const renameTo = ref('');

const unplaced = computed(() =>
    props.students.filter((s) => s.teamId === null),
);
const byTeam = computed(() =>
    props.teams.map((t) => ({
        ...t,
        members: props.students
            .filter((s) => s.teamId === t.id)
            .sort(
                (a, b) =>
                    Object.keys(props.seats).indexOf(a.seat ?? '') -
                    Object.keys(props.seats).indexOf(b.seat ?? ''),
            ),
    })),
);
const seatKeys = computed(() => Object.keys(props.seats));

const doneText = computed(() => {
    const d = props.done;
    if (!d) {
        return null;
    }
    if (d.startsWith('placed:')) {
        const n = Number(d.slice(7));
        return `${n} student${n === 1 ? '' : 's'} placed on teams.`;
    }
    return (
        {
            'nobody-to-place': 'Everyone is already on a team.',
            'join-link': 'The join link is updated.',
            'team-added': 'A team was added.',
            'team-renamed': 'The team is renamed.',
            'team-deleted': 'The team is deleted.',
            move: 'Moved.',
            block: 'Access paused. They see a note to ask you.',
            unblock: 'Access is back on.',
            remove: 'Removed from the class. Their login stays.',
            'reset-password': null,
        } as Record<string, string | null>
    )[d];
});

const loginUrl = computed(() =>
    typeof window === 'undefined'
        ? '/login'
        : `${window.location.origin}/login`,
);

const newLogins = computed(() =>
    (props.added ?? []).filter((a) => a.password !== null),
);

function post(
    url: string,
    data: Record<string, string | number | boolean | null> = {},
) {
    busy.value = true;
    router.post(url + q, data, {
        preserveScroll: true,
        onFinish: () => (busy.value = false),
    });
}

function add() {
    busy.value = true;
    router.post(
        '/faculty/roster/students' + q,
        {
            list: addForm.list,
            team: addForm.team === '' ? null : Number(addForm.team),
            file: file.value,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                addForm.list = '';
                file.value = null;
            },
            onFinish: () => (busy.value = false),
        },
    );
}

function pickFile(e: Event) {
    const input = e.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
}

function move(s: Student, teamId: string | number, seat?: string) {
    post(`/faculty/roster/students/${s.id}/move`, {
        team: teamId === '' ? null : Number(teamId),
        seat: seat ?? null,
    });
}

function act(s: Student, action: string) {
    const asks: Record<string, string> = {
        remove: `Take ${s.name} out of the class? Their login stays, and you can add them back by email.`,
        'reset-password': `Give ${s.name} a new password? The old one stops working.`,
        block: `Pause ${s.name}'s access? They can't open Halden until you switch it back on.`,
    };
    if (asks[action] && !window.confirm(asks[action])) {
        return;
    }
    post(`/faculty/roster/students/${s.id}/${action}`);
}

function formTeams() {
    if (
        !window.confirm(
            `Put the ${unplaced.value.length} students who aren't on a team into teams of five? Empty seats on existing teams fill first.`,
        )
    ) {
        return;
    }
    post('/faculty/roster/form-teams');
}

function startRename(t: { id: number; name: string }) {
    renaming.value = t.id;
    renameTo.value = t.name;
}

function rename(id: number) {
    busy.value = true;
    router.post(
        `/faculty/roster/teams/${id}` + q,
        { name: renameTo.value },
        {
            preserveScroll: true,
            onSuccess: () => (renaming.value = null),
            onFinish: () => (busy.value = false),
        },
    );
}

function deleteTeam(t: { id: number; name: string }) {
    if (!window.confirm(`Delete ${t.name}?`)) {
        return;
    }
    busy.value = true;
    router.delete(`/faculty/roster/teams/${t.id}` + q, {
        preserveScroll: true,
        onFinish: () => (busy.value = false),
    });
}

function joinLink(off = false) {
    if (
        props.section.joinUrl &&
        !off &&
        !window.confirm('Make a new join link? The old one stops working.')
    ) {
        return;
    }
    post('/faculty/roster/join-link', { off });
}

async function copy(text: string) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        window.prompt('Copy this:', text);
    }
}

function downloadLogins() {
    const rows = [
        ['Name', 'Email', 'Password', 'Log in at'],
        ...newLogins.value.map((a) => [
            a.name,
            a.email,
            a.password ?? '',
            `${window.location.origin}/login`,
        ]),
    ];
    const csv = rows
        .map((r) => r.map((c) => `"${c.replace(/"/g, '""')}"`).join(','))
        .join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    a.download = `${props.section.name.replace(/[^\w]+/g, '-')}-logins.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}
</script>

<template>
    <Head :title="`${section.name} · Students and teams`" />
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
                    path="/faculty/roster"
                />
                <span class="hx-hint" style="color: #b8c4c9"
                    >Students and teams</span
                >
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

        <main class="mx-auto max-w-[1100px] px-4 py-7 md:px-5">
            <p
                v-if="doneText"
                class="mb-4 rounded-md px-4 py-3 text-[14px]"
                style="background: var(--hx-teal-wash)"
            >
                {{ doneText }}
            </p>
            <p v-if="errors.roster" class="hx-error mb-4">
                {{ errors.roster }}
            </p>

            <div
                v-if="newPassword"
                class="mb-5 rounded-md px-4 py-3 text-[14px]"
                style="
                    background: var(--hx-amber-soft);
                    color: var(--hx-amber-text);
                "
            >
                New password for <strong>{{ newPassword.name }}</strong> ({{
                    newPassword.email
                }}), shown once:
                <code class="hx-mono">{{ newPassword.password }}</code>
                <button
                    type="button"
                    class="ml-2 underline"
                    @click="copy(newPassword.password)"
                >
                    Copy
                </button>
            </div>

            <section
                v-if="added"
                class="hx-card mb-6"
                style="border-color: var(--hx-amber-text)"
            >
                <h2 class="hx-h2">
                    {{ added.length }}
                    {{ added.length === 1 ? 'student' : 'students' }} handled,
                    {{ newLogins.length }} new
                    {{ newLogins.length === 1 ? 'login' : 'logins' }}
                </h2>
                <p v-if="newLogins.length" class="hx-hint mt-1">
                    These passwords are shown once. Download the list or copy
                    them now, then hand each student theirs. They log in at
                    <code class="hx-mono">{{ loginUrl }}</code
                    >.
                </p>
                <div v-if="newLogins.length" class="mt-2">
                    <button
                        type="button"
                        class="hx-btn hx-btn-primary"
                        @click="downloadLogins"
                    >
                        Download the list (CSV)
                    </button>
                </div>
                <table class="hx-tbl mt-3 w-full">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Password</th>
                            <th scope="col">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in added" :key="a.email">
                            <td>{{ a.name }}</td>
                            <td>{{ a.email }}</td>
                            <td>
                                <code v-if="a.password" class="hx-mono">{{
                                    a.password
                                }}</code>
                            </td>
                            <td class="hx-hint">{{ a.note }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="rejected.length" class="hx-error mt-3">
                    Skipped, no email found: {{ rejected.join(' · ') }}
                </p>
            </section>

            <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <section class="hx-card">
                    <h1 class="hx-h1">Add students</h1>
                    <p class="hx-hint mt-1">
                        One per line: an email, or
                        <code class="hx-mono">Name &lt;email&gt;</code>, or
                        <code class="hx-mono">email, name</code>. Or upload a
                        CSV with those columns. New emails get a login and a
                        password shown once; students who already have a login
                        are simply added.
                    </p>
                    <textarea
                        v-model="addForm.list"
                        class="hx-in hx-in-wide mt-3"
                        rows="6"
                        placeholder="ada@university.edu&#10;Grace Hopper <grace@university.edu>"
                    ></textarea>
                    <span v-if="errors.list" class="hx-error block">{{
                        errors.list
                    }}</span>
                    <div class="mt-3 flex flex-wrap items-end gap-3">
                        <label class="block">
                            <span class="hx-hint">CSV file (optional)</span>
                            <input
                                type="file"
                                accept=".csv,.txt,text/csv"
                                class="block text-[13px]"
                                @change="pickFile"
                            />
                            <span v-if="errors.file" class="hx-error block">{{
                                errors.file
                            }}</span>
                        </label>
                        <label class="block">
                            <span class="hx-hint">Put them on</span>
                            <select
                                v-model="addForm.team"
                                class="hx-in"
                                style="width: 100%; max-width: 200px"
                            >
                                <option value="">No team yet</option>
                                <option
                                    v-for="t in teams"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.name }}
                                </option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="hx-btn hx-btn-primary"
                            :disabled="busy || (!addForm.list.trim() && !file)"
                            @click="add"
                        >
                            Add students
                        </button>
                    </div>
                    <p v-if="section.seats" class="hx-hint mt-3">
                        {{ students.length }} of {{ section.seats }} seats
                        taken.
                    </p>
                </section>

                <section class="hx-card">
                    <h2 class="hx-h2">Join link</h2>
                    <p class="hx-hint mt-1">
                        Students who open this link make their own login and
                        land in the class, not yet on a team. Share it in your
                        course site or by email.
                    </p>
                    <div v-if="section.joinUrl" class="mt-3">
                        <code
                            class="hx-mono block rounded px-2 py-1 text-[13px] break-all"
                            style="background: var(--hx-teal-wash)"
                            >{{ section.joinUrl }}</code
                        >
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="hx-btn hx-btn-primary"
                                @click="copy(section.joinUrl)"
                            >
                                Copy the link
                            </button>
                            <button
                                type="button"
                                class="hx-btn hx-btn-outline"
                                :disabled="busy"
                                @click="joinLink()"
                            >
                                New link
                            </button>
                            <button
                                type="button"
                                class="hx-btn hx-btn-outline"
                                :disabled="busy"
                                @click="joinLink(true)"
                            >
                                Switch it off
                            </button>
                        </div>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="hx-btn hx-btn-primary mt-3"
                        :disabled="busy"
                        @click="joinLink()"
                    >
                        Make a join link
                    </button>
                </section>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
                <h2 class="hx-h1">
                    Teams
                    <span class="hx-hint font-normal"
                        >· {{ students.length }} students,
                        {{ unplaced.length }} not on a team</span
                    >
                </h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="hx-btn hx-btn-primary"
                        :disabled="busy || unplaced.length === 0"
                        @click="formTeams"
                    >
                        Form teams
                    </button>
                    <button
                        type="button"
                        class="hx-btn hx-btn-outline"
                        :disabled="busy"
                        @click="post('/faculty/roster/teams')"
                    >
                        Add an empty team
                    </button>
                </div>
            </div>
            <p class="hx-hint mt-1">
                "Form teams" shuffles everyone who isn't on a team into teams of
                five and gives out the five seats. One or two left over join a
                full team as a sixth on Trading &amp; finance; three or four
                make a smaller team with a seat or two empty. You can move
                anyone afterwards.
            </p>

            <section
                v-if="unplaced.length"
                class="hx-card mt-4"
                style="border-style: dashed"
            >
                <h3 class="hx-h2">Not on a team yet</h3>
                <table class="hx-tbl mt-2 w-full">
                    <tbody>
                        <tr v-for="s in unplaced" :key="s.id">
                            <td>
                                {{ s.name }}
                                <span
                                    v-if="s.blocked"
                                    class="hx-badge hx-badge-ask ml-1"
                                    >paused</span
                                >
                                <span class="hx-hint block">{{ s.email }}</span>
                            </td>
                            <td>
                                <select
                                    class="hx-in"
                                    style="width: 100%; max-width: 200px"
                                    :disabled="busy"
                                    @change="
                                        move(
                                            s,
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="">Put on a team…</option>
                                    <option
                                        v-for="t in teams"
                                        :key="t.id"
                                        :value="t.id"
                                    >
                                        {{ t.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    class="underline"
                                    :disabled="busy"
                                    @click="
                                        act(s, s.blocked ? 'unblock' : 'block')
                                    "
                                >
                                    {{
                                        s.blocked
                                            ? 'Resume access'
                                            : 'Pause access'
                                    }}
                                </button>
                                ·
                                <button
                                    type="button"
                                    class="underline"
                                    :disabled="busy"
                                    @click="act(s, 'reset-password')"
                                >
                                    New password
                                </button>
                                ·
                                <button
                                    type="button"
                                    class="underline"
                                    :disabled="busy"
                                    @click="act(s, 'remove')"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <p v-if="teams.length === 0" class="hx-hint mt-4">
                No teams yet. "Form teams" makes them, or add an empty team and
                place students by hand.
            </p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <section v-for="t in byTeam" :key="t.id" class="hx-card">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <form
                            v-if="renaming === t.id"
                            class="flex gap-2"
                            @submit.prevent="rename(t.id)"
                        >
                            <input
                                v-model="renameTo"
                                class="hx-in"
                                maxlength="60"
                            />
                            <button
                                type="submit"
                                class="hx-btn hx-btn-primary"
                                :disabled="busy || !renameTo.trim()"
                            >
                                Save
                            </button>
                            <button
                                type="button"
                                class="hx-btn hx-btn-outline"
                                @click="renaming = null"
                            >
                                Cancel
                            </button>
                        </form>
                        <h3 v-else class="hx-h2">
                            {{ t.name }}
                            <span class="hx-hint font-normal"
                                >· {{ t.members.length }}</span
                            >
                        </h3>
                        <div v-if="renaming !== t.id" class="text-[13px]">
                            <button
                                type="button"
                                class="underline"
                                @click="startRename(t)"
                            >
                                Rename
                            </button>
                            <template v-if="t.members.length === 0">
                                ·
                                <button
                                    type="button"
                                    class="underline"
                                    :disabled="busy"
                                    @click="deleteTeam(t)"
                                >
                                    Delete
                                </button>
                            </template>
                        </div>
                    </div>
                    <span
                        v-if="errors.name && renaming === t.id"
                        class="hx-error"
                        >{{ errors.name }}</span
                    >
                    <p v-if="t.members.length === 0" class="hx-hint mt-2">
                        Empty.
                    </p>
                    <table v-else class="hx-tbl mt-2 w-full">
                        <tbody>
                            <tr v-for="s in t.members" :key="s.id">
                                <td>
                                    {{ s.name }}
                                    <span
                                        v-if="s.blocked"
                                        class="hx-badge hx-badge-ask ml-1"
                                        >paused</span
                                    >
                                    <span class="hx-hint block">{{
                                        s.email
                                    }}</span>
                                </td>
                                <td>
                                    <select
                                        class="hx-in"
                                        style="width: 100%; max-width: 220px"
                                        :value="s.seat"
                                        :disabled="busy"
                                        @change="
                                            move(
                                                s,
                                                t.id,
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option
                                            v-for="k in seatKeys"
                                            :key="k"
                                            :value="k"
                                        >
                                            {{ seats[k] }}
                                        </option>
                                    </select>
                                    <select
                                        class="hx-in mt-1 block"
                                        style="width: 100%; max-width: 220px"
                                        :value="t.id"
                                        :disabled="busy"
                                        @change="
                                            move(
                                                s,
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option value="">Off the team</option>
                                        <option
                                            v-for="o in teams"
                                            :key="o.id"
                                            :value="o.id"
                                        >
                                            {{ o.name }}
                                        </option>
                                    </select>
                                </td>
                                <td
                                    class="text-right text-[13px] whitespace-nowrap"
                                >
                                    <button
                                        type="button"
                                        class="block underline"
                                        :disabled="busy"
                                        @click="
                                            act(
                                                s,
                                                s.blocked ? 'unblock' : 'block',
                                            )
                                        "
                                    >
                                        {{
                                            s.blocked
                                                ? 'Resume access'
                                                : 'Pause access'
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="block underline"
                                        :disabled="busy"
                                        @click="act(s, 'reset-password')"
                                    >
                                        New password
                                    </button>
                                    <button
                                        type="button"
                                        class="block underline"
                                        :disabled="busy"
                                        @click="act(s, 'remove')"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </main>
    </div>
</template>
