<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/ui/Card.vue';
import { icons } from '@/Support/icons';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
});

/*
 * Grouped, not one long run. "Items" and "Item groups" belong together; your
 * own password does not belong next to who else can sign in. The headings are
 * what someone would say out loud about what they came here to change.
 */
const groups = computed(() =>
    [
        {
            title: 'The restaurant',
            hint: 'Names and places',
            links: [
                {
                    href: '/admin/settings/business',
                    icon: 'Store',
                    title: 'Restaurant name',
                    hint: 'The name on the sign-in screen, the app header and every PDF.',
                    show: props.can.settings,
                },
                {
                    href: '/admin/settings/branches',
                    icon: 'Store',
                    title: 'Branches',
                    hint: 'Names, phone numbers and each daily cut-off time.',
                    show: props.can.branches,
                },
            ],
        },
        {
            title: 'What can be asked for',
            hint: 'The list every branch sees',
            links: [
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
            ],
        },
        {
            title: 'People',
            hint: 'Who can sign in',
            links: [
                {
                    href: '/admin/settings/users',
                    icon: 'Users',
                    title: 'Everyone',
                    hint: 'Who can sign in, and what they are allowed to do.',
                    show: props.can.users,
                },
            ],
        },
        {
            title: 'You',
            hint: 'Only affects your own account',
            links: [
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
            ],
        },
    ]
        .map((group) => ({ ...group, links: group.links.filter((link) => link.show) }))
        .filter((group) => group.links.length),
);
</script>

<template>
    <AdminLayout title="Settings" subtitle="Everything you can change about how the app behaves">
        <Head title="Settings" />

        <div class="grid items-start gap-4 xl:grid-cols-2">
            <Card
                v-for="group in groups"
                :key="group.title"
                :title="group.title"
                :hint="group.hint"
                :padded="false"
            >
                <div class="divide-y divide-line">
                    <Link
                        v-for="link in group.links"
                        :key="link.href"
                        :href="link.href"
                        class="flex min-h-touch items-center gap-3 px-4 py-3 transition hover:bg-page sm:px-5"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-primary-light text-primary"
                        >
                            <component :is="icons[link.icon]" :size="18" aria-hidden="true" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-body font-medium text-ink">{{ link.title }}</span>
                            <span class="block text-helper text-ink-soft">{{ link.hint }}</span>
                        </span>

                        <ChevronRight :size="16" class="shrink-0 text-ink-muted" aria-hidden="true" />
                    </Link>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
