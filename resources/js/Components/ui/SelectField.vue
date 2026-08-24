<script setup>
import { useId } from 'vue';

defineProps({
    modelValue: { type: [String, Number, null], default: null },
    label: { type: String, required: true },
    options: { type: Array, required: true }, // [{ value, label }]
    error: { type: String, default: null },
    hint: { type: String, default: null },
    placeholder: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const id = useId();
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-helper text-ink-soft">{{ label }}</label>

        <select
            :id="id"
            :value="modelValue"
            class="min-h-control w-full rounded-control border bg-surface px-4 text-body text-ink transition focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
            :class="error ? 'border-rejected' : 'border-line'"
            :aria-invalid="error ? 'true' : undefined"
            @change="emit('update:modelValue', $event.target.value)"
        >
            <option v-if="placeholder" value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <p v-if="error" class="mt-2 text-helper text-rejected">{{ error }}</p>
        <p v-else-if="hint" class="mt-2 text-helper text-ink-soft">{{ hint }}</p>
    </div>
</template>
