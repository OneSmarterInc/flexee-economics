<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Registration unavailable',
        description: 'Accounts are managed by each institution.',
    },
});
</script>

<template>
    <Head title="Registration unavailable" />

    <div class="space-y-4 text-center">
        <p class="text-muted-foreground text-sm">
            Halden accounts are created through an authorized tenant
            administrator.
        </p>
        <TextLink :href="login()" class="underline underline-offset-4"
            >Log in</TextLink
        >
    </div>
</template>
