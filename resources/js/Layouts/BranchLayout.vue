<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { icons } from '@/Support/icons';

import BottomNav from '@/Components/ui/BottomNav.vue';
import OfflineBanner from '@/Components/ui/OfflineBanner.vue';
import SoundIndicator from '@/Components/ui/SoundIndicator.vue';
import ToastHost from '@/Components/ui/ToastHost.vue';
import { useRealtime } from '@/Composables/useRealtime';

const props = defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    // A back arrow instead of the branch name, for detail screens on a phone.
    back: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const business = computed(() => page.props.business ?? {});
const currentPath = computed(() => page.url.split('?')[0]);

useRealtime();

// Four is all a phone bar can hold. "Ask for stock" is the big button on the
// screen itself, not a tab.
const navItems = computed(() => [
    { label: 'Home', href: '/b', icon: 'Home', match: ['/b'] },
    { label: 'Requests', href: '/b/requests', icon: 'ClipboardList', match: ['/b/requests'] },
    { label: 'Receive', href: '/b/receive', icon: 'PackageCheck', match: ['/b/receive'] },
    { label: 'More', href: '/b/more', icon: 'Menu', match: ['/b/more'] },
]);

/*
 * On a laptop there is room for everything at once, so the branch app gets the
 * same shell as the main store rather than a phone screen stretched wide.
 */
const sidebarItems = [
    { label: 'Home', href: '/b', icon: 'Home', match: ['/b'] },
    { label: 'Ask for stock', href: '/b/ask', icon: 'Plus', match: ['/b/ask'] },
    { label: 'My requests', href: '/b/requests', icon: 'ClipboardList', match: ['/b/requests'] },
    { label: 'Receive delivery', href: '/b/receive', icon: 'PackageCheck', match: ['/b/receive'] },
    { label: 'Stock left here', href: '/b/stock', icon: 'Boxes', match: ['/b/stock'] },
    { label: 'Thrown away', href: '/waste', icon: 'Trash2', match: ['/waste'] },
    { label: 'Bought locally', href: '/local-purchases', icon: 'ShoppingCart', match: ['/local-purchases'] },
    { label: 'Your details', href: '/settings/profile', icon: 'Users', match: ['/settings/profile'] },
    { label: 'Sound', href: '/settings/sound', icon: 'Bell', match: ['/settings/sound'] },
];

function matchLength(item, path) {
    return item.match.reduce((best, prefix) => {
        const hit = path === prefix || path.startsWith(`${prefix}/`);
        return hit ? Math.max(best, prefix.length) : best;
    }, 0);
}

// Most specific wins, so /b/ask does not also light up Home.
function isActive(item) {
    const path = currentPath.value;
    const mine = matchLength(item, path);

    if (mine === 0) return false;

    return !sidebarItems.some((other) => matchLength(other, path) > mine);
}
</script>

<template>
    <div class="min-h-dvh bg-page">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-primary focus:px-4 focus:py-3 focus:text-body focus:text-white"
        >
            Skip to the page
        </a>

        <!-- Laptop only: the same shell the main store gets. -->
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-sidebar flex-col bg-shell lg:flex">
            <div class="flex items-center gap-3 px-5 py-5">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-white">
                    <component :is="icons.Boxes" :size="20" class="text-shell" aria-hidden="true" />
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-heading text-white">{{ user.branch?.name }}</span>
                    <span class="block truncate text-helper text-shell-text">{{ business.name }}</span>
                </span>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2" aria-label="Main">
                <Link
                    v-for="item in sidebarItems"
                    :key="item.href"
                    :href="item.href"
                    class="group flex min-h-touch items-center gap-3 rounded-control px-3 text-body transition"
                    :class="
                        isActive(item)
                            ? 'bg-primary font-medium text-white'
                            : 'text-shell-text hover:bg-shell-soft hover:text-white'
                    "
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <component :is="icons[item.icon]" :size="18" aria-hidden="true" />
                    <span class="flex-1 truncate">{{ item.label }}</span>
                    <ChevronRight
                        :size="16"
                        class="shrink-0 transition"
                        :class="isActive(item) ? 'opacity-70' : 'opacity-0 group-hover:opacity-50'"
                        aria-hidden="true"
                    />
                </Link>
            </nav>

            <div class="border-t border-shell-line px-3 py-3">
                <div class="px-2 pb-2">
                    <p class="truncate text-body font-medium text-white">{{ user.name }}</p>
                    <p class="truncate text-helper text-shell-text">{{ user.phone }}</p>
                </div>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    type="button"
                    class="flex min-h-touch w-full items-center gap-3 rounded-control px-3 text-body text-shell-text transition hover:bg-shell-soft hover:text-white"
                >
                    <component :is="icons.LogOut" :size="18" aria-hidden="true" />
                    Sign out
                </Link>
            </div>
        </aside>

        <div class="lg:pl-sidebar">
            <header class="sticky top-0 z-20 border-b border-line bg-page/90 pt-safe backdrop-blur">
                <div
                    class="mx-auto flex min-h-[60px] max-w-3xl items-center gap-3 px-4 lg:min-h-[64px] lg:max-w-none lg:px-6"
                >
                    <Link
                        v-if="props.back"
                        :href="props.back"
                        class="-ml-2 flex h-touch w-touch items-center justify-center rounded-control text-ink hover:bg-surface lg:hidden"
                        aria-label="Back"
                    >
                        <ChevronLeft :size="22" />
                    </Link>

                    <div class="min-w-0 flex-1">
                        <p v-if="props.title" class="truncate text-heading text-ink lg:text-title">
                            {{ props.title }}
                        </p>
                        <template v-else>
                            <p class="truncate text-heading text-ink lg:text-title">
                                {{ user.branch?.name }}
                            </p>
                            <p class="truncate text-helper text-ink-soft lg:hidden">{{ user.first_name }}</p>
                        </template>

                        <p v-if="props.subtitle" class="hidden truncate text-helper text-ink-soft lg:block">
                            {{ props.subtitle }}
                        </p>
                    </div>

                    <SoundIndicator />

                    <!-- On a laptop the main action belongs up here, not pinned
                         to the bottom of a tall, mostly empty screen. -->
                    <div v-if="$slots.action" class="hidden lg:block">
                        <slot name="action" />
                    </div>

                    <slot name="header-action" />
                </div>

                <OfflineBanner />
            </header>

            <main
                id="main"
                tabindex="-1"
                class="mx-auto max-w-3xl px-4 py-4 lg:max-w-none lg:px-6 lg:py-6"
                :class="$slots.action ? 'pb-52 lg:pb-8' : 'pb-28 lg:pb-8'"
            >
                <slot />
            </main>
        </div>

        <!-- Phone: the one primary action, full width, inside thumb reach. -->
        <div
            v-if="$slots.action"
            class="fixed inset-x-0 z-30 border-t border-line bg-surface px-4 py-3 lg:hidden"
            :style="{ bottom: 'calc(64px + env(safe-area-inset-bottom, 0px))' }"
        >
            <div class="mx-auto max-w-3xl">
                <slot name="action" />
            </div>
        </div>

        <div class="lg:hidden">
            <BottomNav :items="navItems" />
        </div>

        <ToastHost raised />
    </div>
</template>
