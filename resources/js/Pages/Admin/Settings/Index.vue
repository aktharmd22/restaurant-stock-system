<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Store, Palette } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const links = [
    {
        href: '/admin/settings/business',
        icon: Store,
        title: 'Restaurant name',
        hint: 'The name on the sign-in screen, the app header and every PDF.',
    },
    {
        href: '/design',
        icon: Palette,
        title: 'Design reference',
        hint: 'Every part the screens are built from.',
    },
];
</script>

<template>
    <AdminLayout title="Settings">
        <Head title="Settings" />

        <div class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
            <Link
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="flex min-h-touch items-center gap-4 p-card transition hover:bg-page"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary-light text-primary">
                    <component :is="link.icon" :size="20" aria-hidden="true" />
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
