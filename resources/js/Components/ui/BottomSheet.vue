<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

/**
 * Bottom sheets instead of modals: a thumb reaches the bottom of a phone,
 * not the middle of it.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: null },
    description: { type: String, default: null },
});

const emit = defineEmits(['close']);
const panel = ref(null);

function onKeydown(event) {
    if (event.key === 'Escape') emit('close');
}

watch(
    () => props.open,
    (isOpen) => {
        if (typeof document === 'undefined') return;

        document.body.style.overflow = isOpen ? 'hidden' : '';
        if (isOpen) {
            document.addEventListener('keydown', onKeydown);
            requestAnimationFrame(() => panel.value?.focus());
        } else {
            document.removeEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <div
                class="absolute inset-0 bg-ink/40"
                aria-hidden="true"
                @click="emit('close')"
            />

            <div
                ref="panel"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                :aria-label="title ?? undefined"
                class="relative flex max-h-[88vh] w-full animate-slide-up flex-col rounded-t-card bg-surface shadow-sheet focus:outline-none sm:max-w-lg sm:rounded-card"
            >
                <div class="flex items-start gap-3 border-b border-line px-card py-4 lg:px-card-lg">
                    <div class="flex-1">
                        <h2 v-if="title" class="text-heading text-ink">{{ title }}</h2>
                        <p v-if="description" class="mt-1 text-helper text-ink-soft">{{ description }}</p>
                    </div>

                    <button
                        type="button"
                        class="-mr-2 flex h-touch w-touch items-center justify-center rounded-control text-ink-soft hover:bg-page"
                        aria-label="Close"
                        @click="emit('close')"
                    >
                        <X :size="24" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-card py-4 lg:px-card-lg">
                    <slot />
                </div>

                <div v-if="$slots.footer" class="border-t border-line px-card py-4 pb-safe lg:px-card-lg">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
