<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    categories: { type: Array, required: true },
    lowCount: { type: Number, default: 0 },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => reload({ search: value || undefined }), 300);
});

function reload(changes = {}) {
    router.get('/b/stock', { ...props.filters, ...changes }, { preserveState: true, replace: true });
}
</script>

<template>
    <BranchLayout title="Stock left here" back="/b/more">
        <Head title="Stock left here" />

        <div class="relative">
            <Search :size="20" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-muted" />
            <input
                v-model="search"
                type="search"
                placeholder="Find an item"
                aria-label="Find an item"
                class="min-h-control w-full rounded-control border border-line bg-surface pl-12 pr-4 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
            />
        </div>

        <div class="no-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="min-h-touch shrink-0 rounded-full border px-4 text-body transition"
                :class="
                    filters.category === category.id
                        ? 'border-primary bg-primary-light font-medium text-primary'
                        : 'border-line bg-surface text-ink-soft'
                "
                @click="reload({ category: filters.category === category.id ? undefined : category.id })"
            >
                {{ category.name }}
            </button>
        </div>

        <p v-if="lowCount > 0" class="mt-4 rounded-control bg-partial-bg p-3 text-body text-partial">
            {{ lowCount }} item<span v-if="lowCount !== 1">s</span> running low.
        </p>

        <div v-if="rows.length" class="mt-4 space-y-2">
            <SpineCard v-for="row in rows" :key="row.id" :status="row.is_low ? 'low' : 'approved'">
                <div class="flex items-center justify-between gap-3 p-card">
                    <div class="min-w-0">
                        <p class="text-body font-medium text-ink">{{ row.name }}</p>
                        <p class="text-helper text-ink-soft">
                            {{ row.category }}
                            <span v-if="row.use_by"> · use by {{ row.use_by }}</span>
                        </p>
                    </div>
                    <p class="shrink-0 text-qty tabular" :class="row.is_low ? 'text-partial' : 'text-ink'">
                        {{ row.on_hand_text }}
                    </p>
                </div>
            </SpineCard>
        </div>

        <EmptyState
            v-else
            class="mt-4"
            icon="Boxes"
            title="Nothing here yet"
            message="Stock shows up once you confirm a delivery."
        />

        <template #action>
            <AppButton href="/b/ask" size="lg" block>Ask for stock</AppButton>
        </template>
    </BranchLayout>
</template>
