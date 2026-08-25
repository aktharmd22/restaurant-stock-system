<script setup>
import { computed } from 'vue';
import { RefreshCw, WifiOff } from 'lucide-vue-next';
import { useOfflineQueue } from '@/Composables/useOfflineQueue';

/**
 * Says what has happened and what happens next - never just "offline".
 */
const { state, flush } = useOfflineQueue();

const show = computed(() => !state.online || state.pending > 0);
</script>

<template>
    <div
        v-if="show"
        class="flex items-center gap-3 border-b border-waiting/20 bg-waiting-bg px-4 py-3"
        role="status"
        aria-live="polite"
    >
        <WifiOff :size="20" class="shrink-0 text-waiting" aria-hidden="true" />

        <p class="min-w-0 flex-1 text-body text-waiting">
            <template v-if="!state.online && state.pending > 0">
                No internet. We saved {{ state.pending }}
                {{ state.pending === 1 ? 'request' : 'requests' }} — they will send when you are
                back online.
            </template>
            <template v-else-if="!state.online"> No internet. You can keep working. </template>
            <template v-else>
                {{ state.pending }} saved {{ state.pending === 1 ? 'request' : 'requests' }} waiting
                to send.
            </template>
        </p>

        <button
            v-if="state.online && state.pending > 0"
            type="button"
            class="flex min-h-touch shrink-0 items-center gap-2 rounded-control border border-waiting/30 bg-surface px-3 text-body font-medium text-waiting"
            :disabled="state.sending"
            @click="flush"
        >
            <RefreshCw :size="18" :class="state.sending ? 'animate-spin' : ''" />
            Send now
        </button>
    </div>
</template>
