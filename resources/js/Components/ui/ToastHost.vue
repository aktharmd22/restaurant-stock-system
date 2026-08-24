<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, Info, X, XCircle } from 'lucide-vue-next';
import { useToast } from '@/Composables/useToast';

// `raised` lifts the toasts clear of the bottom navigation on branch screens.
defineProps({
    raised: { type: Boolean, default: false },
});

const { toasts, push, dismiss } = useToast();
const page = usePage();

const icons = { success: CheckCircle2, error: XCircle, info: Info };
const tones = {
    success: 'border-approved/25 text-approved',
    error: 'border-rejected/25 text-rejected',
    info: 'border-primary/25 text-primary',
};

// Server-side flash messages become toasts, so a redirect after an action
// always confirms what happened.
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) push({ message: flash.success, type: 'success' });
        if (flash.error) push({ message: flash.error, type: 'error', duration: 7000 });
        if (flash.info) push({ message: flash.info, type: 'info' });
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 px-4 pb-4 pb-safe sm:items-end"
            :class="raised ? 'mb-[72px] sm:mb-0' : ''"
            role="status"
            aria-live="polite"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="animate-toast-in pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-card border bg-surface p-4 shadow-float"
                :class="tones[toast.type]"
            >
                <component :is="icons[toast.type]" :size="20" class="mt-0.5 shrink-0" aria-hidden="true" />

                <p class="flex-1 text-body text-ink">{{ toast.message }}</p>

                <button
                    v-if="toast.action"
                    type="button"
                    class="min-h-touch shrink-0 rounded-control px-3 text-body font-medium text-primary hover:bg-primary-light"
                    @click="toast.action.onClick(); dismiss(toast.id)"
                >
                    {{ toast.action.label }}
                </button>

                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control text-ink-muted hover:text-ink"
                    aria-label="Close"
                    @click="dismiss(toast.id)"
                >
                    <X :size="18" />
                </button>
            </div>
        </div>
    </Teleport>
</template>
