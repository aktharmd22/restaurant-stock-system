<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { Minus, Plus } from 'lucide-vue-next';

/**
 * Quantity control for people with wet hands, in a hurry, on a small phone.
 * Big targets, hold to run up fast, and the number itself is tappable for the
 * rare case where someone really does want to type.
 */
const props = defineProps({
    modelValue: { type: Number, default: 0 },
    step: { type: Number, default: 1 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: null },
    unit: { type: String, default: '' },
    decimals: { type: Number, default: 1 },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: 'Quantity' },
});

const emit = defineEmits(['update:modelValue']);

const editing = ref(false);
const draft = ref('');
const numberInput = ref(null);

let holdTimer = null;
let repeatTimer = null;
let repeats = 0;

const round = (n) => {
    const factor = 10 ** props.decimals;
    return Math.round(n * factor) / factor;
};

const display = computed(() => {
    const value = round(props.modelValue ?? 0);
    return props.decimals > 0 ? String(parseFloat(value.toFixed(props.decimals))) : String(value);
});

const canDecrease = computed(() => !props.disabled && props.modelValue > props.min);
const canIncrease = computed(
    () => !props.disabled && (props.max === null || props.modelValue < props.max),
);

function apply(direction, multiplier = 1) {
    const next = round((props.modelValue ?? 0) + direction * props.step * multiplier);
    const clamped = Math.max(props.min, props.max !== null ? Math.min(props.max, next) : next);

    if (clamped !== props.modelValue) {
        emit('update:modelValue', clamped);
    }
}

function startHold(direction) {
    if (props.disabled) return;

    apply(direction);
    repeats = 0;

    holdTimer = setTimeout(() => {
        repeatTimer = setInterval(() => {
            repeats += 1;
            // Speed up after a second of holding, so 40 kg is not 40 taps.
            apply(direction, repeats > 12 ? 5 : 1);
        }, 100);
    }, 420);
}

function stopHold() {
    clearTimeout(holdTimer);
    clearInterval(repeatTimer);
    holdTimer = null;
    repeatTimer = null;
}

async function startEditing() {
    if (props.disabled) return;
    draft.value = display.value;
    editing.value = true;
    await nextTick();
    numberInput.value?.select();
}

function commitEditing() {
    editing.value = false;
    const parsed = parseFloat(String(draft.value).replace(',', '.'));
    if (Number.isNaN(parsed)) return;

    const clamped = Math.max(
        props.min,
        props.max !== null ? Math.min(props.max, round(parsed)) : round(parsed),
    );
    emit('update:modelValue', clamped);
}

onBeforeUnmount(stopHold);
</script>

<template>
    <div
        class="inline-flex shrink-0 items-center rounded-control border border-line bg-surface"
        :class="disabled ? 'opacity-60' : ''"
    >
        <button
            type="button"
            class="flex h-touch w-touch shrink-0 items-center justify-center rounded-l-control text-ink transition active:scale-[0.97] disabled:text-ink-muted"
            :disabled="!canDecrease"
            :aria-label="`Less ${label}`"
            @pointerdown="startHold(-1)"
            @pointerup="stopHold"
            @pointerleave="stopHold"
            @pointercancel="stopHold"
            @contextmenu.prevent
        >
            <Minus :size="24" />
        </button>

        <div class="min-w-[84px] border-x border-line px-2 text-center">
            <input
                v-if="editing"
                ref="numberInput"
                v-model="draft"
                type="text"
                inputmode="decimal"
                class="h-touch w-full border-0 bg-transparent p-0 text-center text-qty text-ink focus:ring-0"
                :aria-label="label"
                @blur="commitEditing"
                @keydown.enter.prevent="commitEditing"
            />
            <button
                v-else
                type="button"
                class="flex h-touch w-full items-center justify-center gap-1 text-qty tabular text-ink"
                :aria-label="`${label}: ${display} ${unit}. Tap to type a number.`"
                @click="startEditing"
            >
                {{ display }}
                <span v-if="unit" class="text-helper font-normal text-ink-soft">{{ unit }}</span>
            </button>
        </div>

        <button
            type="button"
            class="flex h-touch w-touch shrink-0 items-center justify-center rounded-r-control text-ink transition active:scale-[0.97] disabled:text-ink-muted"
            :disabled="!canIncrease"
            :aria-label="`More ${label}`"
            @pointerdown="startHold(1)"
            @pointerup="stopHold"
            @pointerleave="stopHold"
            @pointercancel="stopHold"
            @contextmenu.prevent
        >
            <Plus :size="24" />
        </button>
    </div>
</template>
