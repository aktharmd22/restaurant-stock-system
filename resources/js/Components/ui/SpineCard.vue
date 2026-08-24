<script setup>
import { computed } from 'vue';
import { statusMeta } from '@/Support/status';

/**
 * The signature element of the whole app: a white card with a 4px coloured
 * spine down its left edge. From arm's length you can read a list of these
 * by spine colour alone, before reading a single word.
 */
const props = defineProps({
    status: { type: String, default: null },
    // Use a button/link when the whole card is tappable.
    as: { type: String, default: 'div' },
    interactive: { type: Boolean, default: false },
});

const spineColor = computed(() => (props.status ? statusMeta(props.status).spine : '#E8EAED'));
</script>

<template>
    <component
        :is="as"
        class="spine block w-full text-left"
        :class="
            interactive
                ? 'transition active:scale-[0.99] hover:border-ink-muted/40 focus-visible:ring-2 focus-visible:ring-primary'
                : ''
        "
        :style="{ '--spine-color': spineColor }"
    >
        <div class="pl-3">
            <slot />
        </div>
    </component>
</template>
