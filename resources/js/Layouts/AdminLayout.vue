<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
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

const can = computed(() => user.value.can ?? {});

/*
 * The sidebar has room for everything.
 *
 * Three of these sections are really a folder of pages, and burying them one
 * click deep meant nobody found Suppliers or Item groups without going the
 * long way round. They open in place instead, and only ever list doors this
 * person can actually walk through.
 */
const sidebarItems = computed(() => [
    ...navItems.slice(0, 4),
    {
        label: 'Purchase',
        href: '/admin/purchase',
        icon: 'ShoppingCart',
        match: ['/admin/purchase', '/admin/suppliers'],
        children: [
            { label: 'Orders', href: '/admin/purchase', match: ['/admin/purchase'] },
            { label: 'What to buy', href: '/admin/purchase/suggestions', match: ['/admin/purchase/suggestions'] },
            { label: 'Suppliers', href: '/admin/suppliers', match: ['/admin/suppliers'] },
        ],
    },
    {
        label: 'History',
        href: '/admin/history',
        icon: 'History',
        match: ['/admin/history'],
        children: [
            { label: 'Stock movements', href: '/admin/history', match: ['/admin/history'] },
            { label: 'Record changes', href: '/admin/history/changes', match: ['/admin/history/changes'] },
        ],
    },
    { label: 'Reports', href: '/admin/reports', icon: 'FileText', match: ['/admin/reports'] },
    { label: 'Thrown away', href: '/waste', icon: 'Trash2', match: ['/waste'] },
    { label: 'Bought locally', href: '/local-purchases', icon: 'ShoppingCart', match: ['/local-purchases'] },
    {
        ...navItems[4],
        children: [
            { label: 'Restaurant name', href: '/admin/settings/business', match: ['/admin/settings/business'], show: can.value.settings },
            { label: 'Branches', href: '/admin/settings/branches', match: ['/admin/settings/branches'], show: can.value.branches },
            { label: 'Items', href: '/admin/settings/items', match: ['/admin/settings/items'], show: can.value.settings },
            { label: 'Item groups', href: '/admin/settings/categories', match: ['/admin/settings/categories'], show: can.value.settings },
            { label: 'People', href: '/admin/settings/users', match: ['/admin/settings/users'], show: can.value.users },
            { label: 'Your details', href: '/settings/profile', match: ['/settings/profile'] },
            { label: 'Sound', href: '/settings/sound', match: ['/settings/sound'] },
        ].filter((child) => child.show !== false),
    },
]);

function matchLength(item, path) {
    return item.match.reduce((best, prefix) => {
        const hit = path === prefix || path.startsWith(`${prefix}/`);
        return hit ? Math.max(best, prefix.length) : best;
    }, 0);
}

/*
 * Every nav prefix starts with /admin, so a plain "does it match" test lights
 * up Dashboard on every admin page. The most specific match wins instead, and
 * a section's own children count as candidates - otherwise Purchase and
 * "Suppliers" would both light up at once.
 */
const allMatchable = computed(() =>
    sidebarItems.value.flatMap((item) => [item, ...(item.children ?? [])]),
);

function isActive(item) {
    const path = currentPath.value;
    const mine = matchLength(item, path);

    if (mine === 0) return false;

    return !allMatchable.value.some((other) => matchLength(other, path) > mine);
}

// A section counts as "you are in here" when the page belongs to any of it.
function inSection(item) {
    return (
        matchLength(item, currentPath.value) > 0 ||
        (item.children ?? []).some((child) => matchLength(child, currentPath.value) > 0)
    );
}

const openSections = ref(new Set());

// Opening the page you are on: the section you are inside starts open.
watch(
    currentPath,
    () => {
        const here = sidebarItems.value.find((item) => item.children && inSection(item));

        if (here) openSections.value = new Set([here.label]);
    },
    { immediate: true },
);

function toggleSection(item) {
    // One at a time. Three sections open at once ran the list off the bottom
    // of the panel, and nobody needs two folders open to find one page.
    openSections.value = openSections.value.has(item.label) ? new Set() : new Set([item.label]);
}

const isOpen = (item) => openSections.value.has(item.label);
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
            <div class="flex items-center gap-3 px-5 py-5" :title="business.name">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-white">
                    <component :is="icons.Boxes" :size="20" class="text-shell" aria-hidden="true" />
                </span>
                <!-- Two lines rather than an ellipsis: a restaurant's own name
                     is the one thing on this screen that should never be cut. -->
                <span class="min-w-0 flex-1 text-body font-semibold leading-tight text-white line-clamp-2">
                    {{ business.name }}
                </span>
            </div>

            <!-- Tight enough that the longest menu anyone has - the owner's,
                 with Settings open - still fits a laptop without scrolling. -->
            <nav class="shell-scroll flex-1 space-y-0.5 overflow-y-auto px-3 py-2" aria-label="Main">
                <div v-for="item in sidebarItems" :key="item.label">
                    <!-- A section that holds pages: going there and opening it
                         are two different taps, so they are two targets. -->
                    <div
                        v-if="item.children"
                        class="flex min-h-touch items-center rounded-control transition"
                        :class="
                            isActive(item)
                                ? 'bg-primary text-white'
                                : 'text-shell-text hover:bg-shell-soft hover:text-white'
                        "
                    >
                        <Link
                            :href="item.href"
                            class="flex min-h-touch flex-1 items-center gap-3 rounded-l-control px-3 text-body"
                            :class="isActive(item) ? 'font-medium' : ''"
                            :aria-current="isActive(item) ? 'page' : undefined"
                        >
                            <component :is="icons[item.icon]" :size="18" aria-hidden="true" />
                            <span class="flex-1 truncate">{{ item.label }}</span>
                        </Link>

                        <button
                            type="button"
                            class="flex h-touch w-10 shrink-0 items-center justify-center rounded-r-control"
                            :aria-expanded="isOpen(item)"
                            :aria-label="`${isOpen(item) ? 'Hide' : 'Show'} what is under ${item.label}`"
                            @click="toggleSection(item)"
                        >
                            <ChevronDown
                                :size="16"
                                class="transition duration-200"
                                :class="isOpen(item) ? 'rotate-180' : 'opacity-60'"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <Link
                        v-else
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

                    <!-- The rule down the left says these belong to the section
                         above without needing a second icon each. -->
                    <div
                        v-if="item.children && isOpen(item)"
                        class="ml-6 mt-1 space-y-0.5 border-l border-shell-line pl-3"
                    >
                        <Link
                            v-for="child in item.children"
                            :key="child.href"
                            :href="child.href"
                            class="flex min-h-[38px] items-center rounded-control px-3 text-body transition"
                            :class="
                                isActive(child)
                                    ? 'bg-shell-soft font-medium text-white'
                                    : 'text-shell-text hover:bg-shell-soft hover:text-white'
                            "
                            :aria-current="isActive(child) ? 'page' : undefined"
                        >
                            <span class="truncate">{{ child.label }}</span>
                        </Link>
                    </div>
                </div>
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
                <!--
                    Two rows on a phone, one on a laptop.
                    Buttons like "Carry on counting" do not shrink, so sharing
                    a line with them squeezed the page title down to fifty
                    pixels - "Stock on hand" arrived as "Sto…". The actions get
                    their own row instead, and the title gets the width.
                -->
                <div
                    class="grid grid-cols-[auto_1fr_auto] items-center gap-x-3 gap-y-2 px-4 py-3 lg:flex lg:min-h-[64px] lg:gap-3 lg:px-6 lg:py-0"
                >
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-control bg-shell lg:hidden"
                    >
                        <component :is="icons.Boxes" :size="18" class="text-white" aria-hidden="true" />
                    </span>

                    <div class="min-w-0 lg:flex-1">
                        <h1 class="truncate text-title text-ink">{{ props.title }}</h1>
                        <p v-if="props.subtitle" class="truncate text-helper text-ink-soft">
                            {{ props.subtitle }}
                        </p>
                    </div>

                    <SoundIndicator />

                    <div
                        v-if="$slots['header-action']"
                        class="col-span-3 flex flex-wrap items-center gap-2 lg:col-auto"
                    >
                        <slot name="header-action" />
                    </div>
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
