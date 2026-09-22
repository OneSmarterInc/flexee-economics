<script setup lang="ts">
defineProps<{
    field: {
        key: string;
        label: string;
        type: string;
        required: boolean;
        help_text?: string | null;
        unit?: string | null;
        options?: Array<{ value: string; label: string } | string>;
    };
    modelValue: unknown;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: unknown];
}>();

function inputType(type: string): string {
    if (['integer', 'decimal', 'percentage', 'currency'].includes(type)) {
        return 'number';
    }

    return 'text';
}
</script>

<template>
    <label class="block rounded-md border p-3">
        <span class="text-sm font-medium">
            {{ field.label }}
            <span v-if="field.required" class="text-muted-foreground">*</span>
        </span>
        <span
            v-if="field.help_text"
            class="text-muted-foreground mt-1 block text-xs"
        >
            {{ field.help_text }}
        </span>

        <select
            v-if="field.type === 'select' || field.type === 'radio'"
            class="bg-background mt-3 w-full rounded-md border px-3 py-2 text-sm"
            :disabled="disabled"
            :value="(modelValue as string | number | undefined) ?? ''"
            @change="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLSelectElement).value,
                )
            "
        >
            <option value="">Select</option>
            <option
                v-for="option in field.options ?? []"
                :key="typeof option === 'string' ? option : option.value"
                :value="typeof option === 'string' ? option : option.value"
            >
                {{ typeof option === 'string' ? option : option.label }}
            </option>
        </select>

        <input
            v-else-if="field.type === 'boolean'"
            class="mt-3 h-4 w-4"
            type="checkbox"
            :disabled="disabled"
            :checked="Boolean(modelValue)"
            @change="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).checked,
                )
            "
        />

        <div v-else class="mt-3 flex gap-2">
            <input
                class="bg-background min-w-0 flex-1 rounded-md border px-3 py-2 text-sm"
                :type="inputType(field.type)"
                :disabled="disabled"
                :value="(modelValue as string | number | undefined) ?? ''"
                @input="
                    emit(
                        'update:modelValue',
                        ($event.target as HTMLInputElement).value,
                    )
                "
            />
            <span
                v-if="field.unit"
                class="text-muted-foreground self-center text-sm"
            >
                {{ field.unit }}
            </span>
        </div>
    </label>
</template>
