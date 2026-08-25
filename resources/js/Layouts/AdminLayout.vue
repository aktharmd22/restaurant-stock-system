<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { icons } from '@/Support/icons';

import BottomNav from '@/Components/ui/BottomNav.vue';
import OfflineBanner from '@/Components/ui/OfflineBanner.vue';
import SoundIndicator from '@/Components/ui/SoundIndicator.vue';
import ToastHost from '@/Components/ui/ToastHost.vue';
import { useRealtime } from '@/Composables/useRealtime';

const props = defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const business = computed(() => page.props.business ?? {});
const currentPath = computed(() => page.url.split('?')[0]);

useRealtime();

// Five is the most a phone bar can hold without the labels turning to mush.
const navItems = [
    { label: 'Dashboard', href: '/admin', icon: 'LayoutDashboard', match: ['/admin'] },
    { label: 'Requests', href: '/admin/requests', icon: 'Inbox', match: ['/admin/requests'] },
    { label: 'Dispatch', href: '/admin/dispatch', icon: 'Truck', match: ['/admin/dispatch'] },
    { label: 'Stock', href: '/admin/stock', icon: 'Boxes', match: ['/admin/stock'] },
    { label: 'Settings', href: '/admin/settings', icon: 'Settings', match: ['/admin/settings'] },
];

// The sidebar has room for everything.
const sidebarItems = [
    ...navItems.slice(0, 4),
    { label: 'Purchase', href: '/admin/purchase', icon: 'ShoppingCart', match: ['/admin/purchase', '/admin/suppliers'] },
    { label: 'History', href: '/admin/history', icon: 'History', match: ['/admin/history'] },
    { label: 'Reports', href: '/admin/reports', icon: 'FileText', match: ['/admin/reports'] },
    { label: 'Thrown away', href: '/waste', icon: 'Trash2', match: ['/waste'] },
    { label: 'Bought locally', href: '/local-purchases', icon: 'ShoppingCart', match: ['/local-purchases'] },
    navItems[4],
];

function matchLength(item, path) {
    return item.match.reduce((best, prefix) => {
        const hit = path === prefix || path.startsWith(`${prefix}/`);
        return hit ? Math.max(best, prefix.length) : best;
    }, 0);
}

// Every nav prefix starts with /admin, so a plain "does it match" test lights
// up Dashboard on every admin page. The most specific match wins instead.
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

        <!-- The dark shell anchors the layout and pushes the work forward. -->
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-sidebar flex-col bg-shell lg:flex">
            <div class="flex items-center gap-3 px-5 py-5">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-white">
                    <component :is="icons.Boxes" :size="20" class="text-shell" aria-hidden="true" />
                </span>
                <span class="truncate text-heading text-white">{{ business.name }}</span>
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
                    <p class="truncate text-helper text-shell-text">
                        {{ user.branch?.name ?? 'Main store' }}
                    </p>
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
                <div class="flex min-h-[64px] items-center gap-3 px-4 lg:px-6">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-control bg-shell lg:hidden"
                    >
                        <component :is="icons.Boxes" :size="18" class="text-white" aria-hidden="true" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-title text-ink">{{ props.title }}</h1>
                        <p v-if="props.subtitle" class="truncate text-helper text-ink-soft">
                            {{ props.subtitle }}
                        </p>
                    </div>

                    <SoundIndicator />
                    <slot name="header-action" />
                </div>

                <OfflineBanner />
            </header>

            <main id="main" tabindex="-1" class="px-4 py-4 pb-28 lg:px-6 lg:py-6 lg:pb-8">
                <slot />
            </main>
        </div>

        <div class="lg:hidden">
            <BottomNav :items="navItems" />
        </div>

        <ToastHost raised />
    </div>
</template>
