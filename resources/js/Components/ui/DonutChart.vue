<script setup>
import { computed } from 'vue';

/**
 * A donut, drawn with stroke-dasharray on a single circle - no path maths, no
 * library, and it stays crisp at any size.
 */
const props = defineProps({
    // [{ name, value, color }]
    slices: { type: Array, required: true },
    centreLabel: { type: String, default: null },
    centreValue: { type: [String, Number], default: null },
});

const RADIUS = 60;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const total = computed(() => props.slices.reduce((sum, s) => sum + Math.max(0, s.value), 0));

const arcs = computed(() => {
    let offset = 0;

    const built = props.slices
        .filter((s) => s.value > 0)
        .map((s) => {
            const share = total.value > 0 ? s.value / total.value : 0;
            const length = share * CIRCUMFERENCE;
            const arc = {
                ...s,
                share,
                percent: Math.round(share * 100),
                dash: `${length} ${CIRCUMFERENCE - length}`,
                // Rotated so the first slice starts at twelve o'clock.
                offset: -offset,
            };
            offset += length;
            return arc;
        });

    // Rounding each slice on its own can total 101%. The biggest slice takes
    // the remainder, because that is where a one-point change shows least.
    const drift = 100 - built.reduce((sum, arc) => sum + arc.percent, 0);

    if (drift !== 0 && built.length) {
        const biggest = built.reduce((a, b) => (b.share > a.share ? b : a));
        biggest.percent += drift;
    }

    return built;
});
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-center sm:justify-center sm:gap-6">
        <div class="relative shrink-0">
            <svg viewBox="0 0 160 160" class="h-40 w-40 animate-draw-in" role="img" aria-label="Share by status">
                <circle cx="80" cy="80" :r="RADIUS" fill="none" stroke="#ECEEF2" stroke-width="22" />

                <g transform="rotate(-90 80 80)">
                    <circle
                        v-for="arc in arcs"
                        :key="arc.name"
                        cx="80"
                        cy="80"
                        :r="RADIUS"
                        fill="none"
                        :stroke="arc.color"
                        stroke-width="22"
                        :stroke-dasharray="arc.dash"
                        :stroke-dashoffset="arc.offset"
                    >
                        <title>{{ arc.name }}: {{ arc.value }} ({{ arc.percent }}%)</title>
                    </circle>
                </g>
            </svg>

            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-qty tabular text-ink">{{ centreValue ?? total }}</span>
                <span v-if="centreLabel" class="text-helper text-ink-soft">{{ centreLabel }}</span>
            </div>
        </div>

        <ul class="w-full space-y-2 sm:w-auto sm:min-w-[150px]">
            <li
                v-for="arc in arcs"
                :key="arc.name"
                class="flex items-center justify-between gap-3 text-helper"
            >
                <span class="flex min-w-0 items-center gap-2 text-ink-soft">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: arc.color }" aria-hidden="true" />
                    <span class="truncate">{{ arc.name }}</span>
                </span>
                <span class="shrink-0 tabular font-medium text-ink">{{ arc.percent }}%</span>
            </li>

            <li v-if="!arcs.length" class="text-helper text-ink-soft">Nothing to show yet.</li>
        </ul>
    </div>
</template>
