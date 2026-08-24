<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps({
    links: { type: Array, required: true },
    meta: { type: Object, default: null },
});
</script>

<template>
    <nav v-if="links.length > 3" class="mt-4 flex items-center justify-between gap-3" aria-label="Pages">
        <Link
            v-if="links[0].url"
            :href="links[0].url"
            class="flex min-h-touch items-center gap-1 rounded-control border border-line bg-surface px-4 text-body text-ink"
        >
            <ChevronLeft :size="20" />
            Back
        </Link>
        <span v-else class="flex min-h-touch items-center px-4 text-body text-ink-muted">
            <ChevronLeft :size="20" />
            Back
        </span>

        <p v-if="meta" class="text-helper tabular text-ink-soft">
            {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total }}
        </p>

        <Link
            v-if="links[links.length - 1].url"
            :href="links[links.length - 1].url"
            class="flex min-h-touch items-center gap-1 rounded-control border border-line bg-surface px-4 text-body text-ink"
        >
            Next
            <ChevronRight :size="20" />
        </Link>
        <span v-else class="flex min-h-touch items-center px-4 text-body text-ink-muted">
            Next
            <ChevronRight :size="20" />
        </span>
    </nav>
</template>
