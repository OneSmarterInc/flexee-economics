<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface ClassRow {
    id: number;
    name: string;
    course: string;
    weeks: number;
    instructor: string;
    instructorEmail: string;
    teams: number;
    students: number;
    seats: number | null;
    advisorsEnabled: boolean;
    where: string;
    started: boolean;
}

interface Instructor {
    id: number;
    name: string;
    email: string;
    role: string;
    classes: string[];
}

const props = defineProps<{
    classes: ClassRow[];
    instructors: Instructor[];
    defaultDeadline: string;
    newPassword: { email: string; password: string } | null;
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const busy = ref(false);

const newClass = reactive({
    name: '',
    course: 'Managerial Economics',
    weeks: 14,
    instructor: props.instructors[0]?.id ?? 0,
    first_deadline: props.defaultDeadline,
    teams: 6,
    seats: '' as string | number,
});

const newInstructor = reactive({ name: '', email: '' });

function createClass() {
    busy.value = true;
    router.post(
        '/admin/classes',
        {
            ...newClass,
            seats: newClass.seats === '' ? null : Number(newClass.seats),
        },
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}

function createInstructor() {
    busy.value = true;
    router.post('/admin/instructors', newInstructor, {
        preserveScroll: true,
        onSuccess: () => {
            newInstructor.name = '';
            newInstructor.email = '';
        },
        onFinish: () => (busy.value = false),
    });
}
</script>

<template>
    <Head title="Halden · Admin" />
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
                <span class="font-semibold">Classes and instructors</span>
            </div>
            <div class="flex gap-4 text-[13px]">
                <a href="/faculty" style="color: var(--hx-mint)"
                    >Faculty board</a
                >
                <a href="/settings/profile" style="color: var(--hx-mint)"
                    >Settings</a
                >
            </div>
        </header>

        <main class="mx-auto max-w-[1100px] px-4 py-7 md:px-5">
            <p
                v-if="newPassword"
                class="mb-5 rounded-md px-4 py-3 text-[14px]"
                style="
                    background: var(--hx-amber-soft);
                    color: var(--hx-amber-text);
                "
            >
                Login for <strong>{{ newPassword.email }}</strong>
                is ready. Password, shown once:
                <code class="hx-mono">{{ newPassword.password }}</code>
                Copy it now and send it to the instructor.
            </p>

            <h1 class="hx-h1">Classes</h1>
            <div class="hx-card mt-3 overflow-x-auto p-0">
                <table class="hx-tbl w-full">
                    <thead>
                        <tr>
                            <th scope="col">Class</th>
                            <th scope="col">Instructor</th>
                            <th scope="col">Length</th>
                            <th scope="col">Teams · students</th>
                            <th scope="col">Where it is</th>
                            <th scope="col">
                                <span class="hx-sr">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="classes.length === 0">
                            <td colspan="6" class="hx-hint">No classes yet.</td>
                        </tr>
                        <tr v-for="c in classes" :key="c.id">
                            <th scope="row" class="font-semibold">
                                {{ c.name }}
                                <span class="hx-hint block font-normal">{{
                                    c.course
                                }}</span>
                            </th>
                            <td>
                                {{ c.instructor }}
                                <span class="hx-hint block">{{
                                    c.instructorEmail
                                }}</span>
                            </td>
                            <td>{{ c.weeks }} weeks</td>
                            <td>
                                {{ c.teams }} · {{ c.students
                                }}<span v-if="c.seats" class="hx-hint">
                                    of {{ c.seats }} seats</span
                                >
                                <span
                                    v-if="!c.advisorsEnabled"
                                    class="hx-hint block"
                                    >Advisors off</span
                                >
                            </td>
                            <td>{{ c.where }}</td>
                            <td>
                                <a :href="`/admin/classes/${c.id}`">Settings</a>
                                <a
                                    class="mt-1 block"
                                    :href="`/faculty?section=${c.id}`"
                                    >Faculty board</a
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[3fr_2fr]">
                <section class="hx-card">
                    <h2 class="hx-h2">New class</h2>
                    <p class="hx-hint mt-1">
                        Fourteen quarters with a deadline every week from the
                        first one. A 7-week class plays two quarters a week.
                        Nothing opens until the instructor opens Quarter 1.
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="hx-hint">Class name</span>
                            <input
                                v-model="newClass.name"
                                class="hx-in hx-in-wide"
                                placeholder="MBA 7250 Spring 2027"
                            />
                            <span v-if="errors.name" class="hx-error">{{
                                errors.name
                            }}</span>
                        </label>
                        <label class="block">
                            <span class="hx-hint">Course name</span>
                            <input
                                v-model="newClass.course"
                                class="hx-in hx-in-wide"
                            />
                        </label>
                        <label class="block">
                            <span class="hx-hint">Instructor</span>
                            <select
                                v-model="newClass.instructor"
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
                            <span v-if="errors.instructor" class="hx-error">{{
                                errors.instructor
                            }}</span>
                        </label>
                        <label class="block">
                            <span class="hx-hint">Length</span>
                            <select
                                v-model="newClass.weeks"
                                class="hx-in hx-in-wide"
                            >
                                <option :value="14">
                                    14 weeks, one quarter a week
                                </option>
                                <option :value="7">
                                    7 weeks, two quarters a week
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="hx-hint"
                                >First deadline (Eastern)</span
                            >
                            <input
                                v-model="newClass.first_deadline"
                                type="datetime-local"
                                class="hx-in hx-in-wide"
                            />
                            <span
                                v-if="errors.first_deadline"
                                class="hx-error"
                                >{{ errors.first_deadline }}</span
                            >
                        </label>
                        <label class="block">
                            <span class="hx-hint"
                                >Teams to create (Team A, Team B, …)</span
                            >
                            <input
                                v-model.number="newClass.teams"
                                type="number"
                                min="0"
                                max="40"
                                class="hx-in"
                            />
                        </label>
                        <label class="block">
                            <span class="hx-hint"
                                >Seats (the most students the class may hold;
                                blank for no limit)</span
                            >
                            <input
                                v-model="newClass.seats"
                                type="number"
                                min="1"
                                max="500"
                                class="hx-in"
                            />
                        </label>
                    </div>
                    <button
                        type="button"
                        class="hx-btn hx-btn-primary mt-4"
                        :disabled="busy || !newClass.name.trim()"
                        @click="createClass"
                    >
                        Create the class
                    </button>
                </section>

                <section class="hx-card">
                    <h2 class="hx-h2">Instructors</h2>
                    <ul class="mt-2 text-[14px] leading-relaxed">
                        <li v-for="i in instructors" :key="i.id">
                            <span class="font-semibold">{{ i.name }}</span>
                            <span class="hx-hint"> · {{ i.email }}</span>
                            <span v-if="i.role === 'admin'" class="hx-hint">
                                · admin</span
                            >
                            <span
                                v-if="i.classes.length"
                                class="hx-hint block"
                                >{{ i.classes.join(', ') }}</span
                            >
                        </li>
                    </ul>
                    <h3 class="hx-h2 mt-5">New instructor login</h3>
                    <p class="hx-hint mt-1">
                        A password is made and shown once. For an existing
                        instructor this gives them a new password.
                    </p>
                    <label class="mt-3 block">
                        <span class="hx-hint">Name</span>
                        <input
                            v-model="newInstructor.name"
                            class="hx-in hx-in-wide"
                        />
                    </label>
                    <label class="mt-2 block">
                        <span class="hx-hint">Email</span>
                        <input
                            v-model="newInstructor.email"
                            type="email"
                            class="hx-in hx-in-wide"
                        />
                        <span v-if="errors.email" class="hx-error">{{
                            errors.email
                        }}</span>
                    </label>
                    <button
                        type="button"
                        class="hx-btn hx-btn-outline mt-3"
                        :disabled="
                            busy ||
                            !newInstructor.name.trim() ||
                            !newInstructor.email.trim()
                        "
                        @click="createInstructor"
                    >
                        Create the login
                    </button>
                </section>
            </div>
        </main>
    </div>
</template>
