<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';

defineProps<{
    foundation?: {
        tenant?: {
            name: string;
            slug: string;
        } | null;
        role: string;
        enrollments: Array<{
            section: string;
            course: string;
            institution: string;
            status: string;
        }>;
        teams: Array<{
            name: string;
            section: string;
            course: string;
        }>;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <section class="rounded-lg border p-5">
            <p class="text-muted-foreground text-sm">
                {{ foundation?.tenant?.name }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold">Halden foundation</h1>
            <p class="text-muted-foreground mt-2 text-sm">
                Platform role: {{ foundation?.role }}
            </p>
        </section>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-lg border p-5">
                <h2 class="font-medium">Course and section access</h2>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="enrollment in foundation?.enrollments"
                        :key="`${enrollment.course}-${enrollment.section}`"
                        class="rounded-md border p-3"
                    >
                        <p class="font-medium">{{ enrollment.course }}</p>
                        <p class="text-muted-foreground text-sm">
                            {{ enrollment.section }} - {{ enrollment.status }}
                        </p>
                    </article>
                    <p
                        v-if="!foundation?.enrollments.length"
                        class="text-muted-foreground text-sm"
                    >
                        No student enrollments assigned.
                    </p>
                </div>
            </section>

            <section class="rounded-lg border p-5">
                <h2 class="font-medium">Team context</h2>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="team in foundation?.teams"
                        :key="team.name"
                        class="rounded-md border p-3"
                    >
                        <p class="font-medium">{{ team.name }}</p>
                        <p class="text-muted-foreground text-sm">
                            {{ team.course }} - {{ team.section }}
                        </p>
                    </article>
                    <p
                        v-if="!foundation?.teams.length"
                        class="text-muted-foreground text-sm"
                    >
                        No team assignment yet.
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
