<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import BottomNav from '@/Components/ui/BottomNav.vue';
import ToastHost from '@/Components/ui/ToastHost.vue';

const props = defineProps({
    title: { type: String, default: null },
    // A back arrow instead of the branch name, for detail screens.
    back: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});

const navItems = computed(() => [
    { label: 'Home', href: '/b', icon: 'Home', match: ['/b'] },
    { label: 'Requests', href: '/b/requests', icon: 'ClipboardList', match: ['/b/requests'] },
    { label: 'Receive', href: '/b/receive', icon: 'PackageCheck', match: ['/b/receive'] },
    { label: 'More', href: '/b/more', icon: 'Menu', match: ['/b/more'] },
]);
</script>

<template>
    <div class="min-h-dvh bg-page">
        <header class="sticky top-0 z-30 border-b border-line bg-surface pt-safe">
            <div class="mx-auto flex min-h-[60px] max-w-3xl items-center gap-3 px-4">
                <Link
                    v-if="props.back"
                    :href="props.back"
                    class="-ml-2 flex h-touch w-touch items-center justify-center rounded-control text-ink hover:bg-page"
                    aria-label="Back"
                >
                    <ChevronLeft :size="24" />
                </Link>

                <div class="min-w-0 flex-1">
                    <p v-if="props.title" class="truncate text-heading text-ink">{{ props.title }}</p>
                    <template v-else>
                        <p class="truncate text-heading text-ink">{{ user.branch?.name }}</p>
                        <p class="truncate text-helper text-ink-soft">{{ user.first_name }}</p>
                    </template>
                </div>

                <slot name="header-action" />
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-4" :class="$slots.action ? 'pb-52' : 'pb-28'">
            <slot />
        </main>

        <!-- The one primary action, full width, always within thumb reach. -->
        <div
            v-if="$slots.action"
            class="fixed inset-x-0 z-30 border-t border-line bg-surface px-4 py-3"
            :style="{ bottom: 'calc(64px + env(safe-area-inset-bottom, 0px))' }"
        >
            <div class="mx-auto max-w-3xl">
                <slot name="action" />
            </div>
        </div>

        <BottomNav :items="navItems" />
        <ToastHost raised />
    </div>
</template>
