<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { statusMeta } from '@/Support/status';

/**
 * One line in a list.
 *
 * Rows live inside a single surface separated by hairlines, rather than each
 * floating in its own rounded card. Fifteen cards on a grey field read as
 * fifteen equally important things; fifteen rows read as a list you can scan.
 *
 * Status is a small dot in a fixed gutter, so the eye can run down one column
 * and find the odd one out without any row raising its voice.
 */
const props = defineProps({
    href: { type: String, default: null },
    status: { type: String, default: null },
    // Row is the click target, so the chevron is only a hint.
    chevron: { type: Boolean, default: true },
});

const dot = computed(() => (props.status ? statusMeta(props.status).spine : null));
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="flex w-full items-center gap-3 px-4 py-3 text-left transition sm:gap-4 sm:px-5"
        :class="href ? 'hover:bg-page focus-visible:bg-page' : ''"
    >
        <span
            v-if="dot"
            class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
            :style="{ backgroundColor: dot }"
            aria-hidden="true"
        />

        <span class="min-w-0 flex-1">
            <slot />
        </span>

        <span v-if="$slots.end" class="flex shrink-0 items-center gap-3 sm:gap-5">
            <slot name="end" />
        </span>

        <ChevronRight
            v-if="href && chevron"
            :size="16"
            class="shrink-0 text-ink-muted"
            aria-hidden="true"
        />
    </component>
</template>
