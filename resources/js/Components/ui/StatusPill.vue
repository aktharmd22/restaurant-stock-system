<script setup>
import { computed } from 'vue';
import { icons } from '@/Support/icons';
import { statusMeta } from '@/Support/status';

const props = defineProps({
    status: { type: String, required: true },
    // Override the word when a screen needs different phrasing.
    label: { type: String, default: null },
    size: { type: String, default: 'md' }, // md | lg
});

const meta = computed(() => statusMeta(props.status));
const IconComponent = computed(() => icons[meta.value.icon] ?? icons.HelpCircle);
const text = computed(() => props.label ?? meta.value.label);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border font-medium whitespace-nowrap"
        :class="[meta.pill, size === 'lg' ? 'px-3.5 py-2 text-body' : 'px-3 py-1.5 text-helper']"
    >
        <component :is="IconComponent" :size="size === 'lg' ? 20 : 16" aria-hidden="true" />
        {{ text }}
    </span>
</template>
