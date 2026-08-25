<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { icons } from '@/Support/icons';

import BottomNav from '@/Components/ui/BottomNav.vue';
import BrandMark from '@/Components/ui/BrandMark.vue';
import OfflineBanner from '@/Components/ui/OfflineBanner.vue';
import SoundIndicator from '@/Components/ui/SoundIndicator.vue';
import ToastHost from '@/Components/ui/ToastHost.vue';
import { useRealtime } from '@/Composables/useRealtime';

const props = defineProps({
    title: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const currentPath = computed(() => page.url.split('?')[0]);

useRealtime();

// Desktop sidebar and mobile bar are driven by one list, so they can never
// disagree about where things are.
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
    { label: 'Reports', href: '/admin/reports', icon: 'FileText', match: ['/admin/reports'] },
    { label: 'Thrown away', href: '/waste', icon: 'Trash2', match: ['/waste'] },
    { label: 'Bought locally', href: '/local-purchases', icon: 'History', match: ['/local-purchases'] },
    navItems[4],
];

function isActive(item) {
    const path = currentPath.value;
    return item.match.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
}
</script>

<template>
    <div class="min-h-dvh bg-page">
        <!-- Keyboard users should not have to tab through the whole nav. -->
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-primary focus:px-4 focus:py-3 focus:text-body focus:text-white"
        >
            Skip to the page
        </a>
        <!-- Desktop sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-line bg-surface lg:flex"
        >
            <div class="border-b border-line px-5 py-4">
                <BrandMark size="sm" />
            </div>

            <nav class="flex-1 overflow-y-auto p-3" aria-label="Main">
                <Link
                    v-for="item in sidebarItems"
                    :key="item.href"
                    :href="item.href"
                    class="mb-1 flex min-h-touch items-center gap-3 rounded-control px-3 text-body transition"
                    :class="
                        isActive(item)
                            ? 'bg-primary-light font-medium text-primary'
                            : 'text-ink-soft hover:bg-page hover:text-ink'
                    "
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <component :is="icons[item.icon]" :size="20" aria-hidden="true" />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="border-t border-line p-3">
                <div class="px-3 py-2">
                    <p class="truncate text-body font-medium text-ink">{{ user.name }}</p>
                    <p class="truncate text-helper text-ink-soft">Main store</p>
                </div>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    type="button"
                    class="flex min-h-touch w-full items-center gap-3 rounded-control px-3 text-body text-ink-soft transition hover:bg-page hover:text-ink"
                >
                    <component :is="icons.LogOut" :size="20" aria-hidden="true" />
                    Sign out
                </Link>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-line bg-surface pt-safe">
                <div class="flex min-h-[60px] items-center gap-3 px-4 lg:px-6">
                    <div class="lg:hidden">
                        <BrandMark size="sm" :show-name="false" />
                    </div>

                    <h1 class="min-w-0 flex-1 truncate text-heading text-ink lg:text-title">
                        {{ props.title }}
                    </h1>

                    <SoundIndicator />
                    <slot name="header-action" />
                </div>

                <OfflineBanner />
            </header>

            <main id="main" tabindex="-1" class="px-4 py-4 pb-28 lg:px-6 lg:py-6 lg:pb-6">
                <slot />
            </main>
        </div>

        <div class="lg:hidden">
            <BottomNav :items="navItems" />
        </div>

        <ToastHost raised />
    </div>
</template>
