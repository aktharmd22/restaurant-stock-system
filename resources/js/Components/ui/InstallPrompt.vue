<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Download, X } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';

/**
 * Branch staff should open this from their home screen, not by finding a
 * bookmark in a browser. The browser only offers the install once and only
 * after a real user gesture, so the offer is caught and shown at a moment
 * that makes sense.
 */
const DISMISSED_KEY = 'install-dismissed';

const available = ref(false);
let deferred = null;

function onPrompt(event) {
    event.preventDefault();
    deferred = event;

    try {
        available.value = localStorage.getItem(DISMISSED_KEY) !== '1';
    } catch {
        available.value = true;
    }
}

async function install() {
    if (!deferred) return;

    deferred.prompt();
    await deferred.userChoice;

    deferred = null;
    available.value = false;
}

function dismiss() {
    available.value = false;

    try {
        localStorage.setItem(DISMISSED_KEY, '1');
    } catch {
        // Nothing to do - it will simply ask again next time.
    }
}

onMounted(() => window.addEventListener('beforeinstallprompt', onPrompt));
onBeforeUnmount(() => window.removeEventListener('beforeinstallprompt', onPrompt));
</script>

<template>
    <div
        v-if="available"
        class="flex items-start gap-3 rounded-card border border-primary/20 bg-primary-light p-card"
    >
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-surface text-primary">
            <Download :size="20" aria-hidden="true" />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-body font-medium text-ink">Put this on your home screen</p>
            <p class="mt-0.5 text-helper text-ink-soft">
                Then it opens like an app, with one tap and no browser in the way.
            </p>

            <div class="mt-3 flex gap-2">
                <AppButton @click="install">Add it</AppButton>
                <AppButton variant="ghost" @click="dismiss">Not now</AppButton>
            </div>
        </div>

        <button
            type="button"
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control text-ink-muted"
            aria-label="Close"
            @click="dismiss"
        >
            <X :size="18" />
        </button>
    </div>
</template>
