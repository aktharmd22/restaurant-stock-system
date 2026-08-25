<script setup>
import { useId } from 'vue';
import { Search, X } from 'lucide-vue-next';

/**
 * The one search box the app uses.
 *
 * Type "search" gives phones the right keyboard, and the clear button is a
 * real 44px target rather than the browser's own tiny cross, which is
 * unhittable with a wet thumb.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Search' },
    placeholder: { type: String, default: 'Search' },
    hideLabel: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const id = useId();
</script>

<template>
    <div>
        <label :for="id" :class="hideLabel ? 'sr-only' : 'mb-1.5 block text-helper text-ink-soft'">
            {{ label }}
        </label>

        <div class="relative">
            <Search
                :size="18"
                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted"
                aria-hidden="true"
            />

            <input
                :id="id"
                :value="modelValue"
                type="search"
                inputmode="search"
                :placeholder="placeholder"
                class="min-h-control w-full rounded-control border border-line bg-surface pl-10 pr-11 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                @input="emit('update:modelValue', $event.target.value)"
            />

            <button
                v-if="modelValue"
                type="button"
                class="absolute right-1 top-1/2 flex h-touch w-touch -translate-y-1/2 items-center justify-center rounded-control text-ink-muted transition hover:text-ink"
                aria-label="Clear the search"
                @click="emit('update:modelValue', '')"
            >
                <X :size="16" />
            </button>
        </div>
    </div>
</template>
