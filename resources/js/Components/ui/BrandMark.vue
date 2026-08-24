<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Boxes } from 'lucide-vue-next';

const props = defineProps({
    size: { type: String, default: 'md' }, // sm | md | lg
    showName: { type: Boolean, default: true },
    onDark: { type: Boolean, default: false },
});

const page = usePage();
const name = computed(() => page.props.business?.name ?? 'Restaurant Stock');

const boxSizes = { sm: 'h-9 w-9', md: 'h-11 w-11', lg: 'h-14 w-14' };
const iconSizes = { sm: 20, md: 24, lg: 28 };
const textSizes = { sm: 'text-body', md: 'text-heading', lg: 'text-title' };
</script>

<template>
    <div class="flex items-center gap-3">
        <span
            class="flex shrink-0 items-center justify-center rounded-control"
            :class="[boxSizes[size], onDark ? 'bg-white/15 text-white' : 'bg-primary text-white']"
        >
            <Boxes :size="iconSizes[size]" aria-hidden="true" />
        </span>

        <span
            v-if="showName"
            class="font-bold leading-tight"
            :class="[textSizes[size], onDark ? 'text-white' : 'text-ink']"
        >
            {{ name }}
        </span>
    </div>
</template>
