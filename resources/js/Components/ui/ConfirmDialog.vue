<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { AlertTriangle } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';

/**
 * "Are you sure?" for the small number of things that cannot be undone.
 *
 * The title names the thing being deleted rather than asking in the abstract,
 * and the confirm button repeats the verb, so nobody agrees to something they
 * have not read. Cancel is the wider target and sits where the thumb is.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: null },
    confirm: { type: String, default: 'Yes, do it' },
    cancel: { type: String, default: 'Keep it' },
    danger: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'close']);

const panel = ref(null);

function onKey(event) {
    if (event.key === 'Escape') emit('close');
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            window.addEventListener('keydown', onKey);
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => panel.value?.focus());
        } else {
            window.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        }
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-4 sm:items-center"
                @click.self="emit('close')"
            >
                <div
                    ref="panel"
                    role="alertdialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                    class="w-full max-w-md rounded-card bg-surface p-card shadow-float outline-none sm:p-card-lg"
                >
                    <div class="flex gap-3">
                        <span
                            v-if="danger"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-rejected-bg text-rejected"
                        >
                            <AlertTriangle :size="20" aria-hidden="true" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-heading text-ink">{{ title }}</h2>
                            <p v-if="message" class="mt-1 text-body text-ink-soft">{{ message }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <AppButton variant="secondary" size="lg" class="sm:w-auto" @click="emit('close')">
                            {{ cancel }}
                        </AppButton>
                        <AppButton
                            :variant="danger ? 'danger' : 'primary'"
                            size="lg"
                            :loading="loading"
                            class="sm:w-auto"
                            @click="emit('confirm')"
                        >
                            {{ confirm }}
                        </AppButton>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
