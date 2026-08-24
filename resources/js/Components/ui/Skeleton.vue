<script setup>
/**
 * Loading states match the shape of the content that is coming, so the screen
 * does not jump when it arrives.
 */
defineProps({
    height: { type: String, default: '16px' },
    width: { type: String, default: '100%' },
    rounded: { type: String, default: '10px' },
});
</script>

<template>
    <div class="skeleton" :style="{ height, width, borderRadius: rounded }" aria-hidden="true" />
</template>
