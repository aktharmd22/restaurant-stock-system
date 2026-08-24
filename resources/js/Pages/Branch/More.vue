<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, LogOut } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import { icons } from '@/Support/icons';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});

const links = [
    {
        href: '/settings/sound',
        icon: 'Bell',
        title: 'Sound',
        hint: 'Turn alert sounds on or off, and set how loud.',
    },
];
</script>

<template>
    <BranchLayout title="More">
        <Head title="More" />

        <div class="rounded-card border border-line bg-surface p-card">
            <p class="text-body font-medium text-ink">{{ user.name }}</p>
            <p class="text-helper text-ink-soft">
                {{ user.branch?.name }} · {{ user.phone }}
            </p>
        </div>

        <div class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
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

        <div class="mt-4 rounded-card border border-line bg-surface p-card">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                type="button"
                class="flex min-h-touch w-full items-center gap-3 rounded-control px-2 text-body text-ink"
            >
                <LogOut :size="20" aria-hidden="true" />
                Sign out
            </Link>
        </div>
    </BranchLayout>
</template>
