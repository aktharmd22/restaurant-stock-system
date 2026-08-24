<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { icons } from '@/Support/icons';

/**
 * A bar at the bottom, not a hamburger menu. Everything important is one tap
 * away and inside thumb reach.
 */
const props = defineProps({
    items: { type: Array, required: true }, // [{ label, href, icon, match, badge }]
});

const page = usePage();

const currentPath = computed(() => page.url.split('?')[0]);

function isActive(item) {
    const path = currentPath.value;
    if (item.match) return item.match.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
    return path === item.href;
}
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface pb-safe"
        aria-label="Main"
    >
        <ul class="mx-auto flex max-w-3xl">
            <li v-for="item in props.items" :key="item.href" class="flex-1">
                <Link
                    :href="item.href"
                    class="relative flex min-h-touch flex-col items-center justify-center gap-1 px-1 py-2.5 text-helper transition"
                    :class="isActive(item) ? 'text-primary font-medium' : 'text-ink-soft'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <span class="relative">
                        <component :is="icons[item.icon]" :size="24" aria-hidden="true" />
                        <span
                            v-if="item.badge"
                            class="absolute -right-2.5 -top-1.5 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rejected px-1 text-[11px] font-bold leading-none text-white"
                        >
                            {{ item.badge > 9 ? '9+' : item.badge }}
                        </span>
                    </span>
                    {{ item.label }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
