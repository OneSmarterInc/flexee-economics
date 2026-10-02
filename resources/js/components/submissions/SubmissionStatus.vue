<script setup lang="ts">
defineProps<{
    status: {
        decision_status: string;
        memo_status: string;
        complete: boolean;
        ready_for_evaluation: boolean;
        resolution_status?: string;
        resolved_at?: string | null;
    };
}>();
</script>

<template>
    <section class="rounded-lg border p-4">
        <h2 class="font-medium">Submission status</h2>
        <div class="mt-3 grid gap-2 text-sm sm:grid-cols-4">
            <p>Decisions: {{ status.decision_status }}</p>
            <p>Memo: {{ status.memo_status }}</p>
            <p>{{ status.complete ? 'Complete' : 'Incomplete' }}</p>
            <p>
                {{
                    status.resolution_status === 'resolved'
                        ? 'Resolved'
                        : 'Not resolved'
                }}
            </p>
        </div>
    </section>
</template>
