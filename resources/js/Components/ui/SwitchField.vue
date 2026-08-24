<script setup>
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, required: true },
    hint: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="props.modelValue"
        :disabled="props.disabled"
        class="flex min-h-touch w-full items-center gap-4 rounded-control text-left disabled:opacity-60"
        @click="emit('update:modelValue', !props.modelValue)"
    >
        <span class="min-w-0 flex-1">
            <span class="block text-body text-ink">{{ props.label }}</span>
            <span v-if="props.hint" class="block text-helper text-ink-soft">{{ props.hint }}</span>
        </span>

        <span
            class="relative h-7 w-12 shrink-0 rounded-full transition"
            :class="props.modelValue ? 'bg-primary' : 'bg-line'"
        >
            <span
                class="absolute top-1 h-5 w-5 rounded-full bg-surface transition-all"
                :class="props.modelValue ? 'left-6' : 'left-1'"
            />
        </span>
    </button>
</template>
