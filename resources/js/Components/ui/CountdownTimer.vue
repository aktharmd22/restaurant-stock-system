<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Clock } from 'lucide-vue-next';

/**
 * Time left before today's cut-off. After it passes, the message says what
 * actually happens next - it never says "too late", because a branch can still
 * send a request at any hour.
 */
const props = defineProps({
    at: { type: String, required: true },
    isPast: { type: Boolean, default: false },
    time: { type: String, required: true },
});

const secondsLeft = ref(0);
let timer = null;

function tick() {
    const target = new Date(props.at).getTime();
    secondsLeft.value = Math.max(0, Math.round((target - Date.now()) / 1000));
}

onMounted(() => {
    tick();
    timer = setInterval(tick, 1000);
});

onBeforeUnmount(() => clearInterval(timer));

const remaining = computed(() => {
    const total = secondsLeft.value;
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    if (hours > 0) return `${hours}h ${minutes}m`;
    if (minutes > 0) return `${minutes}m ${String(seconds).padStart(2, '0')}s`;
    return `${seconds}s`;
});
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-card border p-card"
        :class="props.isPast ? 'border-partial/20 bg-partial-bg' : 'border-line bg-surface'"
    >
        <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control"
            :class="props.isPast ? 'bg-partial/10 text-partial' : 'bg-primary-light text-primary'"
        >
            <Clock :size="20" aria-hidden="true" />
        </span>

        <div class="min-w-0">
            <p class="text-body text-ink">
                <template v-if="props.isPast">
                    Today's {{ props.time }} cut-off has passed.
                </template>
                <template v-else>
                    <span class="tabular font-medium">{{ remaining }}</span> left before the
                    {{ props.time }} cut-off.
                </template>
            </p>
            <p class="text-helper text-ink-soft">
                <template v-if="props.isPast">
                    You can still ask now. It will be marked late and go to the top of the list.
                </template>
                <template v-else> Ask before then and it goes out on the normal run. </template>
            </p>
        </div>
    </div>
</template>
