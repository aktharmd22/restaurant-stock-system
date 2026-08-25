<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { MoreHorizontal } from 'lucide-vue-next';

/**
 * The second and third action on a row.
 *
 * A settings row that shows Edit, New password and Switch off all at once
 * costs three lines on a phone and gives the rare action the same weight as
 * the common one. Edit stays on the row; everything else lives behind this.
 *
 * The menu is teleported to the body and positioned against the viewport, so
 * it is never clipped by the card it sits inside.
 */
defineProps({
    label: { type: String, default: 'More actions' },
});

const open = ref(false);
const trigger = ref(null);
const menu = ref(null);
const position = ref({ top: 0, left: 0 });

const MENU_WIDTH = 208;

async function place() {
    const rect = trigger.value?.getBoundingClientRect();
    if (!rect) return;

    // Right edge of the menu lines up with the button, kept on screen.
    const left = Math.max(8, Math.min(rect.right - MENU_WIDTH, window.innerWidth - MENU_WIDTH - 8));

    position.value = { top: rect.bottom + 4, left };
    await nextTick();

    // Not enough room underneath: sit above the button instead.
    const height = menu.value?.offsetHeight ?? 0;
    if (rect.bottom + 4 + height > window.innerHeight - 8) {
        position.value = { top: Math.max(8, rect.top - height - 4), left };
    }
}

function close() {
    open.value = false;
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
}

async function toggle() {
    if (open.value) return close();

    open.value = true;
    await place();

    // Any scroll or resize moves the row out from under the menu.
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
}

function onPointerDown(event) {
    if (trigger.value?.contains(event.target) || menu.value?.contains(event.target)) return;
    close();
}

onBeforeUnmount(close);
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="flex h-touch w-touch shrink-0 items-center justify-center rounded-control text-ink-soft transition hover:bg-page hover:text-ink"
        :class="open ? 'bg-page text-ink' : ''"
        :aria-label="label"
        aria-haspopup="menu"
        :aria-expanded="open"
        @click="toggle"
    >
        <MoreHorizontal :size="18" />
    </button>

    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-40"
            @pointerdown="onPointerDown"
            @keydown.escape="close"
        >
            <div
                ref="menu"
                role="menu"
                class="fixed z-50 w-52 overflow-hidden rounded-card border border-line bg-surface py-1 shadow-float"
                :style="{ top: `${position.top}px`, left: `${position.left}px` }"
                @click="close"
            >
                <slot />
            </div>
        </div>
    </Teleport>
</template>
