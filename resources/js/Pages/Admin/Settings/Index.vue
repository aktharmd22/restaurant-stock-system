<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { icons } from '@/Support/icons';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
});

const links = computed(() =>
    [
        {
            href: '/admin/settings/business',
            icon: 'Store',
            title: 'Restaurant name',
            hint: 'The name on the sign-in screen, the app header and every PDF.',
            show: props.can.settings,
        },
        {
            href: '/admin/settings/items',
            icon: 'Package',
            title: 'Items',
            hint: 'What branches can ask for, and how much a full shelf is.',
            show: props.can.settings,
        },
        {
            href: '/admin/settings/categories',
            icon: 'ListChecks',
            title: 'Item groups',
            hint: 'The chips branches use to filter the item list.',
            show: props.can.settings,
        },
        {
            href: '/admin/settings/branches',
            icon: 'Store',
            title: 'Branches',
            hint: 'Names, phone numbers and each daily cut-off time.',
            show: props.can.branches,
        },
        {
            href: '/admin/settings/users',
            icon: 'Users',
            title: 'People',
            hint: 'Who can sign in, and what they are allowed to do.',
            show: props.can.users,
        },
        {
            href: '/settings/profile',
            icon: 'Users',
            title: 'Your details',
            hint: 'Your own name, phone number and password.',
            show: true,
        },
        {
            href: '/settings/sound',
            icon: 'Bell',
            title: 'Sound',
            hint: 'Turn alert sounds on or off, and set how loud.',
            show: true,
        },
        {
            href: '/design',
            icon: 'Palette',
            title: 'Design reference',
            hint: 'Every part the screens are built from.',
            show: true,
        },
    ].filter((link) => link.show),
);
</script>

<template>
    <AdminLayout title="Settings">
        <Head title="Settings" />

        <div class="max-w-3xl divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
            <Link
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="flex min-h-touch items-center gap-4 p-card transition hover:bg-page"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary-light text-primary">
                    <component :is="icons[link.icon]" :size="20" aria-hidden="true" />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block text-body font-medium text-ink">{{ link.title }}</span>
                    <span class="block text-helper text-ink-soft">{{ link.hint }}</span>
                </span>

                <ChevronRight :size="20" class="shrink-0 text-ink-muted" aria-hidden="true" />
            </Link>
        </div>
    </AdminLayout>
</template>
