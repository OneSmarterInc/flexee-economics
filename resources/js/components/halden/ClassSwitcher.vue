<script setup lang="ts">
import { router } from '@inertiajs/vue3';

/**
 * The class picker in the faculty headers, shown only to someone with more than one class. Picking one opens the
 * same screen (the board, the roster) for that class.
 */
const props = defineProps<{
    classes: { id: number; name: string; course: string }[];
    current: number;
    path: string;
}>();

function go(e: Event) {
    const id = Number((e.target as HTMLSelectElement).value);
    if (id && id !== props.current) {
        router.visit(`${props.path}?section=${id}`);
    }
}
</script>

<template>
    <label v-if="classes.length > 1" class="inline-flex items-center gap-2">
        <span class="hx-sr">Class</span>
        <select
            class="hx-mono rounded px-2 py-1 text-[13px]"
            style="
                background: #1f3540;
                color: #e6ecef;
                border: 1px solid #3a5160;
                max-width: 260px;
            "
            :value="current"
            @change="go"
        >
            <option v-for="c in classes" :key="c.id" :value="c.id">
                {{ c.name }}
            </option>
        </select>
    </label>
    <span v-else class="font-semibold">{{
        classes.find((c) => c.id === current)?.name ?? ''
    }}</span>
</template>
