<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Card from '@/Components/ui/Card.vue';

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
    router.get('/b/stock', { ...props.filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
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

        <!-- One list, laid out in columns on a wider screen. Hairlines carry
             the rhythm; nothing floats. -->
        <div v-if="rows.length" class="mt-4">
            <Card :padded="false">
                <!-- The right-hand rule on the last column is pushed under the
                     card's own border, so every column reads the same. -->
                <div class="-mr-px grid sm:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="row in rows"
                        :key="row.id"
                        class="flex items-center justify-between gap-3 border-b border-r border-line px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-body font-medium text-ink">{{ row.name }}</p>
                            <p class="truncate text-helper text-ink-soft">
                                {{ row.category }}
                                <span v-if="row.use_by"> · use by {{ row.use_by }}</span>
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-qty tabular" :class="row.is_low ? 'text-partial' : 'text-ink'">
                                {{ row.on_hand_text }}
                            </p>
                            <p v-if="row.is_low" class="text-micro font-medium text-partial">low</p>
                        </div>
                    </div>
                </div>
            </Card>
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
