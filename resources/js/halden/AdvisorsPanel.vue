<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import type { AdvisorsView } from '@/halden/types';

const props = defineProps<{
    advisors: AdvisorsView;
    quarterId: number;
    canAsk: boolean;
    forFaculty: boolean;
}>();

const emit = defineEmits<{ goDecide: [] }>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

const MEETING = 'meeting';
const selectedKey = ref<string>(props.advisors.cards[0]?.key ?? '');
const selected = computed(() =>
    selectedKey.value === MEETING
        ? undefined
        : (props.advisors.cards.find((c) => c.key === selectedKey.value) ??
          props.advisors.cards[0]),
);
const inMeeting = computed(() => selectedKey.value === MEETING);
const question = ref('');
const sending = ref(false);
const thread = ref<HTMLElement | null>(null);

// Who is in the meeting: last time's set, or everyone with answers left, trimmed to the most allowed.
const invited = ref<string[]>(
    props.advisors.meeting.invited.length > 0
        ? [...props.advisors.meeting.invited]
        : props.advisors.cards
              .filter((c) => c.left > 0)
              .slice(0, props.advisors.meeting.max)
              .map((c) => c.key),
);

function toggleInvite(key: string) {
    if (invited.value.includes(key)) {
        invited.value = invited.value.filter((k) => k !== key);
    } else if (invited.value.length < props.advisors.meeting.max) {
        invited.value = [...invited.value, key];
    }
}

const inviteProblem = computed(() => {
    const done = invited.value
        .map((k) => props.advisors.cards.find((c) => c.key === k))
        .find((c) => c && c.left === 0);

    if (done) {
        return t('meeting_done', { name: done.first });
    }

    if (invited.value.length < props.advisors.meeting.min) {
        return t('meeting_too_few');
    }

    return '';
});

const invitedNames = computed(() =>
    invited.value
        .map((k) => props.advisors.cards.find((c) => c.key === k)?.first ?? k)
        .join(', '),
);

function namesOf(keys: string[] | undefined): string {
    return (keys ?? [])
        .map((k) => props.advisors.cards.find((c) => c.key === k)?.first ?? k)
        .join(', ');
}

const canMeet = computed(
    () =>
        props.canAsk &&
        props.advisors.enabled &&
        props.advisors.open &&
        inviteProblem.value === '',
);

const t = (key: string, fill: Record<string, string | number> = {}) =>
    Object.entries(fill).reduce(
        (s, [k, v]) => s.replaceAll(`{${k}}`, String(v)),
        props.advisors.text[key] ?? '',
    );

const canType = computed(
    () =>
        props.canAsk &&
        props.advisors.enabled &&
        props.advisors.open &&
        (selected.value?.left ?? 0) > 0,
);

function scrollDown() {
    nextTick(() => {
        if (thread.value) {
            thread.value.scrollTop = thread.value.scrollHeight;
        }
    });
}

watch(selectedKey, scrollDown);
watch(() => selected.value?.messages.length, scrollDown);
watch(() => props.advisors.meeting.messages.length, scrollDown);

function ask() {
    const card = selected.value;

    if (question.value.trim() === '' || sending.value) {
        return;
    }

    if (!card && !inMeeting.value) {
        return;
    }

    sending.value = true;
    router.post(
        card
            ? `/play/${props.quarterId}/advisors/${card.key}`
            : `/play/${props.quarterId}/meeting`,
        card
            ? { question: question.value }
            : { question: question.value, invited: invited.value },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['advisors', 'errors'],
            onSuccess: () => {
                question.value = '';
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        ask();
    }
}

function timeOf(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('en-US', {
              weekday: 'short',
              hour: 'numeric',
              minute: '2-digit',
          })
        : '';
}
</script>

<template>
    <div class="hx-card">
        <h1 class="hx-h1">Your advisors</h1>
        <p class="hx-p">{{ advisors.text.header }}</p>
        <p class="hx-hint mt-1">
            {{ advisors.text.split_tip }} {{ advisors.text.cost_note }}
        </p>
        <p
            v-if="!advisors.enabled"
            class="mt-3 rounded-md px-3 py-2 text-[14px]"
            style="
                background: var(--hx-amber-soft);
                color: var(--hx-amber-text);
            "
        >
            {{ advisors.text.unavailable }}
        </p>
        <p v-else-if="!advisors.open" class="hx-hint mt-3">
            {{ advisors.text.closed }}
        </p>
    </div>

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[260px_1fr]">
        <nav aria-label="Advisors" class="flex flex-col gap-1.5">
            <button
                type="button"
                class="hx-opt flex items-center gap-3 text-left"
                :aria-pressed="inMeeting"
                @click="selectedKey = MEETING"
            >
                <span
                    class="flex h-9 w-9 flex-none items-center justify-center rounded-full text-[12px] font-semibold"
                    style="
                        background: var(--hx-amber-soft);
                        color: var(--hx-amber-text);
                    "
                    aria-hidden="true"
                    >{{ advisors.cards.length }}</span
                >
                <span class="min-w-0">
                    <span class="block truncate font-semibold">{{
                        advisors.text.meeting_name
                    }}</span>
                    <span class="hx-hint block">{{
                        advisors.text.meeting_role
                    }}</span>
                </span>
            </button>
            <button
                v-for="c in advisors.cards"
                :key="c.key"
                type="button"
                class="hx-opt flex items-center gap-3 text-left"
                :aria-pressed="c.key === selectedKey"
                @click="selectedKey = c.key"
            >
                <span
                    class="flex h-9 w-9 flex-none items-center justify-center rounded-full text-[12px] font-semibold"
                    style="
                        background: var(--hx-teal-soft);
                        color: var(--hx-teal);
                    "
                    aria-hidden="true"
                    >{{ c.initials }}</span
                >
                <span class="min-w-0">
                    <span class="block truncate font-semibold">{{
                        c.name
                    }}</span>
                    <span class="hx-hint block">{{ c.role }}</span>
                    <span
                        class="hx-hint block"
                        :style="
                            c.left === 0 ? 'color: var(--hx-amber-text)' : ''
                        "
                    >
                        {{ t('left', { n: c.left, max: c.limit }) }}
                    </span>
                </span>
            </button>
        </nav>

        <section
            v-if="inMeeting"
            class="hx-card flex min-h-[460px] flex-col p-0"
            :aria-label="advisors.text.meeting_name"
        >
            <header
                class="border-b px-5 py-4"
                style="border-color: var(--hx-line)"
            >
                <div class="font-semibold">
                    {{ advisors.text.meeting_name }}
                </div>
                <p class="mt-1 text-[14px] leading-snug">
                    {{ advisors.text.meeting_intro }}
                </p>
                <fieldset v-if="canAsk && advisors.open" class="mt-3">
                    <legend class="hx-hint">
                        {{ advisors.text.meeting_pick }}
                    </legend>
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                        <label
                            v-for="c in advisors.cards"
                            :key="c.key"
                            class="flex items-center gap-1.5 text-[14px]"
                            :style="
                                c.left === 0 ? 'color: var(--hx-muted)' : ''
                            "
                        >
                            <input
                                type="checkbox"
                                :checked="invited.includes(c.key)"
                                :disabled="
                                    sending ||
                                    c.left === 0 ||
                                    (!invited.includes(c.key) &&
                                        invited.length >= advisors.meeting.max)
                                "
                                @change="toggleInvite(c.key)"
                            />
                            {{ c.first }}
                            <span class="hx-hint">({{ c.left }} left)</span>
                        </label>
                    </div>
                </fieldset>
            </header>

            <div
                ref="thread"
                class="flex flex-1 flex-col gap-3 overflow-y-auto px-5 py-4"
                style="max-height: 520px"
                aria-live="polite"
            >
                <p
                    v-if="advisors.meeting.messages.length === 0"
                    class="hx-hint"
                >
                    {{ advisors.text.meeting_empty }}
                </p>
                <div
                    v-for="m in advisors.meeting.messages"
                    :key="m.id"
                    :class="m.from === 'team' ? 'self-end' : 'self-start'"
                    class="max-w-[85%]"
                >
                    <div class="hx-hint mb-0.5 text-[12px]">
                        {{ m.from === 'notice' ? '' : m.who }}
                        <span v-if="m.at"> · {{ timeOf(m.at) }}</span>
                        <span v-if="m.from === 'team' && m.invited?.length">
                            ·
                            {{
                                t('meeting_with', { names: namesOf(m.invited) })
                            }}</span
                        >
                    </div>
                    <div
                        class="rounded-lg px-3.5 py-2.5 text-[15px] leading-relaxed whitespace-pre-line"
                        :style="
                            m.from === 'team'
                                ? 'background: var(--hx-teal); color: #fff'
                                : m.from === 'advisor'
                                  ? 'background: var(--hx-soft); border: 1px solid var(--hx-line)'
                                  : m.from === 'dropped'
                                    ? 'background: #fff4e5; border: 1px dashed #e9c48f; color: #6a4410'
                                    : 'background: var(--hx-paper); color: var(--hx-muted); font-style: italic'
                        "
                    >
                        {{ m.body || '(no reply)' }}
                    </div>
                    <div
                        v-if="forFaculty && m.from === 'dropped'"
                        class="mt-1 text-[12px]"
                        style="color: #6a4410"
                    >
                        Not shown to the team. Reason: {{ m.reason }}
                    </div>
                </div>
                <div v-if="sending" class="hx-hint self-start italic">
                    {{ advisors.text.meeting_thinking }}
                </div>
            </div>

            <footer
                class="border-t px-5 py-4"
                style="border-color: var(--hx-line)"
            >
                <template v-if="canAsk && advisors.enabled && advisors.open">
                    <label class="hx-sr" for="ask-meeting">{{
                        advisors.text.meeting_placeholder
                    }}</label>
                    <textarea
                        id="ask-meeting"
                        v-model="question"
                        class="hx-in hx-in-wide w-full"
                        rows="3"
                        :placeholder="advisors.text.meeting_placeholder"
                        :disabled="sending || !canMeet"
                        @keydown="onKey"
                    />
                    <div
                        class="mt-2 flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="hx-hint">{{
                            inviteProblem ||
                            t('meeting_with', { names: invitedNames })
                        }}</span>
                        <button
                            type="button"
                            class="hx-btn hx-btn-primary"
                            :disabled="
                                sending || !canMeet || question.trim() === ''
                            "
                            @click="ask"
                        >
                            {{ advisors.text.send }}
                        </button>
                    </div>
                    <p v-if="errors.question" class="hx-error mt-2">
                        {{ errors.question }}
                    </p>
                </template>
                <button
                    type="button"
                    class="mt-2 text-[14px] underline"
                    style="color: var(--hx-teal)"
                    @click="emit('goDecide')"
                >
                    {{ advisors.text.go_decide }}
                </button>
            </footer>
        </section>

        <section
            v-else-if="selected"
            class="hx-card flex min-h-[460px] flex-col p-0"
            :aria-label="`Conversation with ${selected.name}`"
        >
            <header
                class="border-b px-5 py-4"
                style="border-color: var(--hx-line)"
            >
                <div class="font-semibold">
                    {{ selected.name }} · {{ selected.role }}
                </div>
                <div class="mt-1 text-[14px] leading-snug">
                    <span class="font-medium" style="color: var(--hx-teal)"
                        >Good at:</span
                    >
                    {{ selected.good_at }}
                </div>
                <div class="mt-0.5 text-[14px] leading-snug">
                    <span
                        class="font-medium"
                        style="color: var(--hx-amber-text)"
                        >Watch out for:</span
                    >
                    {{ selected.watch_out }}
                </div>
            </header>

            <div
                ref="thread"
                class="flex flex-1 flex-col gap-3 overflow-y-auto px-5 py-4"
                style="max-height: 520px"
                aria-live="polite"
            >
                <p v-if="selected.messages.length === 0" class="hx-hint">
                    Nobody on your team has asked {{ selected.first }} anything
                    yet this quarter.
                </p>
                <div
                    v-for="m in selected.messages"
                    :key="m.id"
                    :class="m.from === 'team' ? 'self-end' : 'self-start'"
                    class="max-w-[85%]"
                >
                    <div class="hx-hint mb-0.5 text-[12px]">
                        {{ m.from === 'notice' ? '' : m.who }}
                        <span v-if="m.at"> · {{ timeOf(m.at) }}</span>
                    </div>
                    <div
                        class="rounded-lg px-3.5 py-2.5 text-[15px] leading-relaxed whitespace-pre-line"
                        :style="
                            m.from === 'team'
                                ? 'background: var(--hx-teal); color: #fff'
                                : m.from === 'advisor'
                                  ? 'background: var(--hx-soft); border: 1px solid var(--hx-line)'
                                  : m.from === 'dropped'
                                    ? 'background: #fff4e5; border: 1px dashed #e9c48f; color: #6a4410'
                                    : 'background: var(--hx-paper); color: var(--hx-muted); font-style: italic'
                        "
                    >
                        {{ m.body || '(no reply)' }}
                    </div>
                    <div
                        v-if="forFaculty && m.from === 'dropped'"
                        class="mt-1 text-[12px]"
                        style="color: #6a4410"
                    >
                        Not shown to the team. Reason: {{ m.reason }}
                    </div>
                </div>
                <div v-if="sending" class="hx-hint self-start italic">
                    {{ t('thinking', { first: selected.first }) }}
                </div>
            </div>

            <footer
                class="border-t px-5 py-4"
                style="border-color: var(--hx-line)"
            >
                <template v-if="canType">
                    <label class="hx-sr" :for="`ask-${selected.key}`">{{
                        t('placeholder', { first: selected.first })
                    }}</label>
                    <textarea
                        :id="`ask-${selected.key}`"
                        v-model="question"
                        class="hx-in hx-in-wide w-full"
                        rows="3"
                        :placeholder="
                            t('placeholder', { first: selected.first })
                        "
                        :disabled="sending"
                        @keydown="onKey"
                    />
                    <div
                        class="mt-2 flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="hx-hint">{{
                            t('left', { n: selected.left, max: selected.limit })
                        }}</span>
                        <button
                            type="button"
                            class="hx-btn hx-btn-primary"
                            :disabled="sending || question.trim() === ''"
                            @click="ask"
                        >
                            {{ advisors.text.send }}
                        </button>
                    </div>
                    <p v-if="errors.question" class="hx-error mt-2">
                        {{ errors.question }}
                    </p>
                </template>
                <p
                    v-else-if="
                        advisors.enabled &&
                        advisors.open &&
                        canAsk &&
                        selected.left === 0
                    "
                    class="hx-hint"
                >
                    {{ t('none_left', { name: selected.first }) }}
                </p>
                <button
                    type="button"
                    class="mt-2 text-[14px] underline"
                    style="color: var(--hx-teal)"
                    @click="emit('goDecide')"
                >
                    {{ advisors.text.go_decide }}
                </button>
            </footer>
        </section>
    </div>
</template>
