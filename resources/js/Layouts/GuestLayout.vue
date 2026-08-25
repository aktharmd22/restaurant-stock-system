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
        <!--
            Desktop only. Because the panel is display:none below lg, browsers
            never fetch the photo for a phone - branch staff on mobile data pay
            nothing for it.

            Photo: Unsplash (unsplash.com/photos/da7dedc7c39a). The Unsplash
            licence allows commercial use with no attribution required.
        -->
        <aside
            class="relative hidden bg-primary bg-cover bg-center p-12 lg:flex lg:flex-col lg:justify-between"
            style="background-image: url('/images/kitchen.jpg')"
        >
            <!--
                A brand tint over the whole photo, then a scrim weighted to the
                bottom where the words are.

                This is the one gradient in the app. A flat wash cannot do the
                job here: the photo has a white chef's coat and stacked plates
                exactly where the headline falls, and no single opacity both
                keeps the photo readable and clears the contrast floor. The
                scrim is confined to this decorative panel - no gradient appears
                anywhere in the app itself.
            -->
            <div class="absolute inset-0 bg-primary/40" aria-hidden="true"></div>
            <div
                class="absolute inset-0"
                aria-hidden="true"
                style="
                    background-image: linear-gradient(
                        to top,
                        rgba(16, 30, 74, 0.97) 0%,
                        rgba(16, 30, 74, 0.9) 34%,
                        rgba(16, 30, 74, 0.25) 72%,
                        rgba(16, 30, 74, 0) 100%
                    );
                "
            ></div>

            <div class="relative">
                <BrandMark size="lg" :show-name="false" on-dark />
            </div>

            <div class="relative">
                <h1 class="max-w-md text-4xl font-bold leading-tight text-white">
                    {{ business.name }}
                </h1>
                <p class="mt-4 max-w-sm text-lg text-white">
                    {{ business.tagline }}
                </p>
            </div>

            <p class="relative text-helper text-white/90">
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
