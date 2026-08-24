<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BrandMark from '@/Components/ui/BrandMark.vue';
import ToastHost from '@/Components/ui/ToastHost.vue';

const page = usePage();
const business = computed(() => page.props.business ?? {});
</script>

<template>
    <div class="min-h-dvh bg-surface lg:grid lg:grid-cols-2">
        <!-- Desktop: a solid panel with the restaurant name. No gradient, no stock photo. -->
        <aside class="hidden bg-primary p-12 lg:flex lg:flex-col lg:justify-between">
            <BrandMark size="lg" :show-name="false" on-dark />

            <div>
                <h1 class="max-w-md text-4xl font-bold leading-tight text-white">
                    {{ business.name }}
                </h1>
                <p class="mt-4 max-w-sm text-lg text-white/80">
                    {{ business.tagline }}
                </p>
            </div>

            <p class="text-helper text-white/70">
                Ask for stock. Get it approved. Know it arrived.
            </p>
        </aside>

        <!-- Mobile: one white column, mark at the top. -->
        <main class="flex min-h-dvh flex-col justify-center px-6 py-10 pt-safe sm:px-10">
            <div class="mx-auto w-full max-w-[400px]">
                <div class="lg:hidden">
                    <BrandMark size="md" />
                    <p class="mt-3 text-body text-ink-soft">{{ business.tagline }}</p>
                </div>

                <div class="mt-8 lg:mt-0">
                    <slot />
                </div>
            </div>
        </main>

        <ToastHost />
    </div>
</template>
