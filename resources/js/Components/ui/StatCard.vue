<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { icons } from '@/Support/icons';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: null },
    tone: { type: String, default: 'neutral' }, // neutral | waiting | approved | partial | rejected | primary
    href: { type: String, default: null },
    hint: { type: String, default: null },
});

const tones = {
    neutral: 'text-ink bg-page',
    primary: 'text-primary bg-primary-light',
    waiting: 'text-waiting bg-waiting-bg',
    approved: 'text-approved bg-approved-bg',
    partial: 'text-partial bg-partial-bg',
    rejected: 'text-rejected bg-rejected-bg',
};

const IconComponent = computed(() => (props.icon ? icons[props.icon] : null));
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="block rounded-card border border-line bg-surface p-card lg:p-card-lg"
        :class="href ? 'transition active:scale-[0.99] hover:border-primary/40' : ''"
    >
        <div class="flex items-start justify-between gap-3">
            <p class="text-body text-ink-soft">{{ label }}</p>
            <span
                v-if="IconComponent"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control"
                :class="tones[tone]"
            >
                <component :is="IconComponent" :size="20" aria-hidden="true" />
            </span>
        </div>

        <p class="mt-2 text-stat tabular text-ink">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-helper text-ink-soft">{{ hint }}</p>
    </component>
</template>
