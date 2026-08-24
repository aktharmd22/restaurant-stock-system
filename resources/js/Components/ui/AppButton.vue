<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger
    size: { type: String, default: 'md' }, // md (48px) | lg (52px)
    block: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    loadingText: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
});

const variants = {
    primary: 'bg-primary text-white border-primary hover:bg-primary-dark',
    secondary: 'bg-surface text-ink border-line hover:bg-page',
    ghost: 'bg-transparent text-primary border-transparent hover:bg-primary-light',
    danger: 'bg-rejected-bg text-rejected border-rejected/20 hover:bg-rejected/10',
};

const component = computed(() => (props.href ? Link : 'button'));
const isBlocked = computed(() => props.disabled || props.loading);
</script>

<template>
    <component
        :is="component"
        :href="href"
        :type="href ? undefined : type"
        :disabled="href ? undefined : isBlocked"
        :aria-busy="loading ? 'true' : undefined"
        class="inline-flex items-center justify-center gap-2 rounded-control border px-5 font-medium transition active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-60"
        :class="[
            variants[variant],
            size === 'lg' ? 'min-h-control text-body' : 'min-h-touch text-body',
            block ? 'w-full' : '',
        ]"
    >
        <Loader2 v-if="loading" :size="20" class="animate-spin" aria-hidden="true" />
        <slot v-if="!loading" name="icon" />
        <span>
            <template v-if="loading && loadingText">{{ loadingText }}</template>
            <slot v-else />
        </span>
    </component>
</template>
