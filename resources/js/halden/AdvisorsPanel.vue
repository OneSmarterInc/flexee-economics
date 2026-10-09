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

const selectedKey = ref<string>(props.advisors.cards[0]?.key ?? '');
const selected = computed(
    () =>
        props.advisors.cards.find((c) => c.key === selectedKey.value) ??
        props.advisors.cards[0],
);
const question = ref('');
const sending = ref(false);
const thread = ref<HTMLElement | null>(null);

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

function ask() {
    const card = selected.value;

    if (!card || question.value.trim() === '' || sending.value) {
        return;
    }

    sending.value = true;
    router.post(
        `/play/${props.quarterId}/advisors/${card.key}`,
        { question: question.value },
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
            v-if="selected"
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
