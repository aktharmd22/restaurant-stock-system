<script setup>
import { Link } from '@inertiajs/vue3';

/** One line in a RowMenu. A link when given an href, a button otherwise. */
defineProps({
    href: { type: String, default: null },
    danger: { type: Boolean, default: false },
});
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :type="href ? undefined : 'button'"
        role="menuitem"
        class="flex min-h-touch w-full items-center gap-2.5 px-3 text-left text-body transition hover:bg-page"
        :class="danger ? 'text-rejected' : 'text-ink'"
    >
        <slot name="icon" />
        <slot />
    </component>
</template>
