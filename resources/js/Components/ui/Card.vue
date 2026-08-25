<script setup>
/**
 * The container everything sits in. One radius, one border, one shadow, so no
 * screen has to invent its own.
 */
defineProps({
    title: { type: String, default: null },
    hint: { type: String, default: null },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
        <header
            v-if="title || $slots.action"
            class="flex flex-wrap items-center justify-between gap-3 px-card py-3.5"
            :class="$slots.default ? 'border-b border-line' : ''"
        >
            <div class="min-w-0">
                <h2 class="truncate text-heading text-ink">{{ title }}</h2>
                <p v-if="hint" class="truncate text-helper text-ink-soft">{{ hint }}</p>
            </div>

            <slot name="action" />
        </header>

        <div :class="padded ? 'p-card' : ''">
            <slot />
        </div>
    </section>
</template>
