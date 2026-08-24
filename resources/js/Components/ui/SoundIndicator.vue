<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BellOff, Volume2, VolumeX } from 'lucide-vue-next';
import { useSound } from '@/Composables/useSound';

/**
 * Says out loud whether sound is actually working.
 *
 * An admin who believes alerts are on when the browser has blocked them will
 * miss requests and never know why - so a blocked state is shown in amber,
 * not hidden.
 */
const page = usePage();
const { state } = useSound();

const user = computed(() => page.props.auth?.user ?? {});

const status = computed(() => {
    if (!user.value.sound_enabled) return 'off';
    if (state.blocked) return 'blocked';
    return 'on';
});

const icons = { on: Volume2, off: VolumeX, blocked: BellOff };

const labels = {
    on: 'Sound is on',
    off: 'Sound is off',
    blocked: 'Tap anywhere to turn sound on',
};
</script>

<template>
    <Link
        href="/settings/sound"
        class="flex h-touch w-touch items-center justify-center rounded-control transition"
        :class="{
            'text-ink-soft hover:bg-page': status === 'on',
            'text-ink-muted hover:bg-page': status === 'off',
            'bg-waiting-bg text-waiting': status === 'blocked',
        }"
        :title="labels[status]"
        :aria-label="labels[status]"
    >
        <component :is="icons[status]" :size="24" aria-hidden="true" />
    </Link>
</template>
