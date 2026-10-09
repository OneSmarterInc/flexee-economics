<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    help: {
        enabled: boolean;
        screen: Record<string, string>;
        faq: { q: string; a: string }[];
    };
}>();

const open = ref(false);
const question = ref('');
const answer = ref<string | null>(null);
const failed = ref(false);
const asking = ref(false);
const openFaq = ref<number | null>(null);

function xsrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

async function ask() {
    if (question.value.trim() === '' || asking.value) {
        return;
    }

    asking.value = true;
    answer.value = null;
    failed.value = false;

    try {
        const res = await fetch('/help', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ question: question.value }),
        });
        const body = res.ok
            ? ((await res.json()) as { answer: string | null })
            : { answer: null };
        answer.value = body.answer;
        failed.value = body.answer === null;
    } catch {
        failed.value = true;
    } finally {
        asking.value = false;
    }
}
</script>

<template>
    <button
        type="button"
        class="rounded px-2.5 py-1 text-[13px]"
        style="background: #2a3b47; color: #e6ecef"
        :aria-expanded="open"
        @click="open = !open"
    >
        ? {{ props.help.screen.button }}
    </button>
    <div
        v-if="open"
        class="fixed inset-0 z-40 flex justify-end"
        style="background: rgba(15, 26, 34, 0.35)"
        @click.self="open = false"
    >
        <aside
            class="hx flex h-full w-full max-w-[440px] flex-col gap-3 overflow-y-auto p-5"
            style="background: var(--hx-paper); color: var(--hx-ink)"
            role="dialog"
            :aria-label="props.help.screen.title"
        >
            <div class="flex items-center justify-between">
                <h2 class="hx-h2">{{ props.help.screen.title }}</h2>
                <button
                    type="button"
                    class="hx-btn hx-btn-outline"
                    @click="open = false"
                >
                    {{ props.help.screen.close }}
                </button>
            </div>
            <ul class="flex flex-col gap-1.5">
                <li
                    v-for="(f, i) in props.help.faq"
                    :key="i"
                    class="hx-card p-3"
                >
                    <button
                        type="button"
                        class="w-full text-left font-semibold"
                        :aria-expanded="openFaq === i"
                        @click="openFaq = openFaq === i ? null : i"
                    >
                        {{ f.q }}
                    </button>
                    <p
                        v-if="openFaq === i"
                        class="mt-1.5 text-[14px] leading-relaxed"
                    >
                        {{ f.a }}
                    </p>
                </li>
            </ul>
            <div class="hx-card p-3">
                <label for="help-q" class="font-semibold">{{
                    props.help.screen.ask_label
                }}</label>
                <p class="hx-hint mt-0.5">{{ props.help.screen.note }}</p>
                <template v-if="props.help.enabled">
                    <textarea
                        id="help-q"
                        v-model="question"
                        class="hx-in hx-in-wide mt-2 w-full"
                        rows="2"
                        :placeholder="props.help.screen.ask_placeholder"
                        maxlength="2000"
                    />
                    <button
                        type="button"
                        class="hx-btn hx-btn-primary mt-2"
                        :disabled="asking || question.trim() === ''"
                        @click="ask"
                    >
                        {{ props.help.screen.send }}
                    </button>
                    <p v-if="asking" class="hx-hint mt-2 italic">
                        {{ props.help.screen.thinking }}
                    </p>
                    <p
                        v-if="answer"
                        class="mt-2 text-[14px] leading-relaxed"
                        aria-live="polite"
                    >
                        {{ answer }}
                    </p>
                    <p
                        v-if="failed"
                        class="mt-2 text-[14px]"
                        style="color: var(--hx-amber-text)"
                        aria-live="polite"
                    >
                        {{ props.help.screen.failed }}
                    </p>
                </template>
                <p v-else class="hx-hint mt-2">
                    {{ props.help.screen.unavailable }}
                </p>
            </div>
        </aside>
    </div>
</template>
