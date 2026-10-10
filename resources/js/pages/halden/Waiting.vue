<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    className: string | null;
    course: string | null;
    reason: 'no-class' | 'no-team' | 'blocked' | 'unpaid';
}>();
</script>

<template>
    <Head title="Halden" />
    <div class="hx flex min-h-screen items-center justify-center px-4">
        <div class="hx-card max-w-[520px]">
            <span
                class="hx-mono text-[13px] tracking-[0.2em]"
                style="color: var(--hx-teal)"
                >HALDEN ENERGY</span
            >
            <h1 class="hx-h1 mt-2">
                {{
                    reason === 'blocked'
                        ? 'Your access is paused'
                        : reason === 'unpaid'
                          ? 'One more step before you start'
                          : reason === 'no-team'
                            ? "You're in, and not on a team yet"
                            : "You're not in a class yet"
                }}
            </h1>
            <p v-if="reason === 'no-team'" class="hx-p mt-3">
                You're enrolled in {{ className }} ({{ course }}). Your
                instructor hasn't put you on a team yet. Once they have, signing
                in brings you to the company. There's nothing to do until then.
            </p>
            <p v-else-if="reason === 'unpaid'" class="hx-p mt-3">
                You're enrolled in {{ className }} ({{ course }}). Your seat
                hasn't been marked as paid yet. Once your instructor marks it,
                signing in brings you to the company. If you've paid, let them
                know.
            </p>
            <p v-else-if="reason === 'blocked'" class="hx-p mt-3">
                Your instructor has paused your access to {{ className }} for
                now. Ask them about it.
            </p>
            <p v-else class="hx-p mt-3">
                This login isn't in a class. If your instructor gave you a join
                link, open it while signed in. Otherwise ask them to add you by
                email.
            </p>
            <div class="mt-5 flex gap-3 text-[14px]">
                <a
                    href="/play"
                    class="hx-btn hx-btn-primary"
                    style="color: #fff"
                    >Check again</a
                >
                <a href="/settings/profile" class="hx-btn hx-btn-outline"
                    >Settings</a
                >
            </div>
        </div>
    </div>
</template>
