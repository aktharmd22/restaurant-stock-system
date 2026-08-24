<script setup>
import { computed } from 'vue';
import { icons } from '@/Support/icons';

/**
 * Empty screens invite an action. They never apologise.
 */
const props = defineProps({
    icon: { type: String, default: 'Inbox' },
    title: { type: String, required: true },
    message: { type: String, default: null },
});

const IconComponent = computed(() => icons[props.icon] ?? icons.Inbox);
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-card border border-line bg-surface px-6 py-12 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
            <component :is="IconComponent" :size="24" aria-hidden="true" />
        </span>

        <p class="mt-4 text-heading text-ink">{{ title }}</p>
        <p v-if="message" class="mt-1 max-w-sm text-body text-ink-soft">{{ message }}</p>

        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
