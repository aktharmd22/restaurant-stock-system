<script setup>
import { computed } from 'vue';

/**
 * A grouped bar chart, drawn by hand in SVG.
 *
 * A charting library would cost more download than the rest of this app's
 * JavaScript put together, for two charts. This is a few dozen lines and it
 * scales to any width without a resize observer.
 */
const props = defineProps({
    // [{ name, color, values: number[] }]
    series: { type: Array, required: true },
    categories: { type: Array, required: true },
    // Formats the value in the y axis and the tooltip title.
    format: { type: Function, default: (value) => String(value) },
});

const W = 680;
const H = 260;
const PAD = { top: 12, right: 8, bottom: 30, left: 60 };

const plot = {
    w: W - PAD.left - PAD.right,
    h: H - PAD.top - PAD.bottom,
};

const max = computed(() => {
    const highest = Math.max(1, ...props.series.flatMap((s) => s.values));
    // Round up to something a person would put on an axis.
    const magnitude = 10 ** Math.floor(Math.log10(highest));
    return Math.ceil(highest / magnitude) * magnitude;
});

const ticks = computed(() => {
    const count = 4;
    return Array.from({ length: count + 1 }, (_, i) => (max.value / count) * i);
});

const groupWidth = computed(() => plot.w / Math.max(1, props.categories.length));
const barWidth = computed(() =>
    Math.max(6, Math.min(18, (groupWidth.value * 0.62) / Math.max(1, props.series.length))),
);

function y(value) {
    return PAD.top + plot.h - (value / max.value) * plot.h;
}

function barX(categoryIndex, seriesIndex) {
    const groupCentre = PAD.left + groupWidth.value * (categoryIndex + 0.5);
    const totalWidth = barWidth.value * props.series.length + 3 * (props.series.length - 1);
    return groupCentre - totalWidth / 2 + seriesIndex * (barWidth.value + 3);
}
</script>

<template>
    <div>
        <svg
            :viewBox="`0 0 ${W} ${H}`"
            class="h-auto w-full animate-draw-in"
            role="img"
            :aria-label="`Bar chart comparing ${series.map((s) => s.name).join(' and ')}`"
        >
            <!-- horizontal guides -->
            <g>
                <line
                    v-for="tick in ticks"
                    :key="`g-${tick}`"
                    :x1="PAD.left"
                    :x2="W - PAD.right"
                    :y1="y(tick)"
                    :y2="y(tick)"
                    stroke="#ECEEF2"
                    stroke-width="1"
                />
                <text
                    v-for="tick in ticks"
                    :key="`t-${tick}`"
                    :x="PAD.left - 8"
                    :y="y(tick) + 4"
                    text-anchor="end"
                    class="fill-ink-muted text-[11px]"
                >
                    {{ format(tick) }}
                </text>
            </g>

            <!-- bars -->
            <g v-for="(category, ci) in categories" :key="category">
                <template v-for="(s, si) in series" :key="`${category}-${s.name}`">
                    <rect
                        :x="barX(ci, si)"
                        :y="y(s.values[ci] ?? 0)"
                        :width="barWidth"
                        :height="Math.max(0, PAD.top + plot.h - y(s.values[ci] ?? 0))"
                        :fill="s.color"
                        rx="4"
                    >
                        <title>{{ category }} · {{ s.name }}: {{ format(s.values[ci] ?? 0) }}</title>
                    </rect>
                </template>

                <text
                    :x="PAD.left + groupWidth * (ci + 0.5)"
                    :y="H - 10"
                    text-anchor="middle"
                    class="fill-ink-soft text-[11px]"
                >
                    {{ category }}
                </text>
            </g>
        </svg>

        <ul class="mt-3 flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
            <li v-for="s in series" :key="s.name" class="flex items-center gap-2 text-helper text-ink-soft">
                <span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: s.color }" aria-hidden="true" />
                {{ s.name }}
            </li>
        </ul>
    </div>
</template>
