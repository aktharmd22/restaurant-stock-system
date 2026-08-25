<script setup>
import { computed } from 'vue';
import { icons } from '@/Support/icons';
import { statusMeta } from '@/Support/status';

/**
 * Status as words, not as a badge.
 *
 * A pill on every row of a list makes every row shout equally, and a page of
 * shouting rows has no hierarchy left. The colour, the glyph and the word are
 * all still here - the brief's rule that colour never travels alone holds -
 * they just stop wearing a coloured box.
 */
const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: null },
    size: { type: String, default: 'md' }, // sm | md
});

const meta = computed(() => statusMeta(props.status));
const IconComponent = computed(() => icons[meta.value.icon] ?? icons.HelpCircle);
const text = computed(() => props.label ?? meta.value.label);

// The pill classes carry a background too; only the ink is wanted here.
const ink = computed(() => meta.value.pill.split(' ').find((c) => c.startsWith('text-')));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 whitespace-nowrap font-medium"
        :class="[ink, size === 'sm' ? 'text-helper' : 'text-body']"
    >
        <component :is="IconComponent" :size="size === 'sm' ? 13 : 15" aria-hidden="true" />
        {{ text }}
    </span>
</template>
