<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { TrendingDown, TrendingUp } from 'lucide-vue-next';
import { icons } from '@/Support/icons';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: null },
    tone: { type: String, default: 'blue' }, // violet | blue | cyan | green | amber | rose
    href: { type: String, default: null },
    hint: { type: String, default: null },

    // Movement against the period before this one.
    delta: { type: Number, default: null },
    deltaLabel: { type: String, default: 'than last month' },
    // Some numbers are better when they fall - waste, for one.
    lowerIsBetter: { type: Boolean, default: false },
});

const tiles = {
    violet: 'bg-tile-violet text-tile-violet-ink',
    blue: 'bg-tile-blue text-tile-blue-ink',
    cyan: 'bg-tile-cyan text-tile-cyan-ink',
    green: 'bg-tile-green text-tile-green-ink',
    amber: 'bg-tile-amber text-tile-amber-ink',
    rose: 'bg-tile-rose text-tile-rose-ink',

    // Older screens still pass the status names.
    neutral: 'bg-tile-blue text-tile-blue-ink',
    primary: 'bg-tile-blue text-tile-blue-ink',
    waiting: 'bg-tile-amber text-tile-amber-ink',
    approved: 'bg-tile-green text-tile-green-ink',
    partial: 'bg-tile-amber text-tile-amber-ink',
    rejected: 'bg-tile-rose text-tile-rose-ink',
};

const IconComponent = computed(() => (props.icon ? icons[props.icon] : null));

const rising = computed(() => (props.delta ?? 0) >= 0);
const good = computed(() => (props.lowerIsBetter ? !rising.value : rising.value));
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="block rounded-card border border-line bg-surface p-card shadow-card transition"
        :class="href ? 'hover:border-primary/40 active:scale-[0.99]' : ''"
    >
        <span
            v-if="IconComponent"
            class="mb-3 flex h-10 w-10 items-center justify-center rounded-control"
            :class="tiles[tone] ?? tiles.blue"
        >
            <component :is="IconComponent" :size="20" aria-hidden="true" />
        </span>

        <p class="text-helper text-ink-soft">{{ label }}</p>
        <p class="mt-1 text-stat tabular text-ink">{{ value }}</p>

        <p
            v-if="delta !== null"
            class="mt-1.5 flex flex-wrap items-center gap-1 text-helper"
            :class="good ? 'text-approved' : 'text-rejected'"
        >
            <component :is="rising ? TrendingUp : TrendingDown" :size="14" aria-hidden="true" />
            <span class="tabular font-medium">{{ Math.abs(delta) }}%</span>
            <span class="text-ink-soft">{{ deltaLabel }}</span>
        </p>

        <p v-else-if="hint" class="mt-1.5 text-helper text-ink-soft">{{ hint }}</p>
    </component>
</template>
