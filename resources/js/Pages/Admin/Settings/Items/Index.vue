<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Package, Plus, Search } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';

const props = defineProps({
    items: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? null);

let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => reload({ search: value }), 300);
});

function pickCategory(id) {
    category.value = category.value === id ? null : id;
    reload({ category: category.value });
}

function reload(extra = {}) {
    router.get(
        '/admin/settings/items',
        { search: search.value || undefined, category: category.value || undefined, ...extra },
        { preserveState: true, preserveScroll: true, replace: true, only: ['items', 'filters'] },
    );
}

function toggle(item) {
    router.post(`/admin/settings/items/${item.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Items">
        <Head title="Items" />

        <template #header-action>
            <AppButton :href="route('admin.items.create')">
                <template #icon><Plus :size="20" /></template>
                Add item
            </AppButton>
        </template>

        <Link
            href="/admin/settings"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Settings
        </Link>

        <div class="max-w-4xl">
            <div class="relative">
                <Search :size="20" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-muted" />
                <input
                    v-model="search"
                    type="search"
                    inputmode="search"
                    placeholder="Search items"
                    aria-label="Search items"
                    class="min-h-control w-full rounded-control border border-line bg-surface pl-12 pr-4 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                />
            </div>

            <div class="no-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="option in categories"
                    :key="option.value"
                    type="button"
                    class="min-h-touch shrink-0 rounded-full border px-4 text-body transition"
                    :class="
                        category === option.value
                            ? 'border-primary bg-primary-light font-medium text-primary'
                            : 'border-line bg-surface text-ink-soft hover:text-ink'
                    "
                    @click="pickCategory(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div v-if="items.data.length" class="mt-4 space-y-2">
                <SpineCard
                    v-for="item in items.data"
                    :key="item.id"
                    :status="item.is_active ? 'approved' : 'cancelled'"
                >
                    <div class="flex flex-wrap items-center gap-3 p-card">
                        <img
                            v-if="item.photo"
                            :src="item.photo"
                            :alt="item.name"
                            loading="lazy"
                            class="h-12 w-12 shrink-0 rounded-control border border-line object-cover"
                        />
                        <span
                            v-else
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-control border border-line bg-page text-ink-muted"
                        >
                            <Package :size="20" aria-hidden="true" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-body font-medium text-ink">{{ item.name }}</p>
                            <p class="text-helper text-ink-soft">
                                {{ item.category }} · sold by {{ item.unit }}
                                <span class="text-ink-muted">
                                    (1 {{ item.unit }} = {{ item.conversion }} {{ item.base_unit }})
                                </span>
                            </p>
                            <p v-if="item.storage_location" class="text-helper text-ink-muted">
                                {{ item.storage_location }}
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            <AppButton variant="secondary" :href="`/admin/settings/items/${item.id}/edit`">
                                Edit
                            </AppButton>
                            <AppButton variant="ghost" @click="toggle(item)">
                                {{ item.is_active ? 'Hide' : 'Show' }}
                            </AppButton>
                        </div>
                    </div>
                </SpineCard>

                <Pagination :links="items.links" :meta="items" />
            </div>

            <EmptyState
                v-else
                class="mt-4"
                icon="Package"
                title="No items found"
                message="Try a different search, or add a new item."
            >
                <template #action>
                    <AppButton :href="route('admin.items.create')">Add item</AppButton>
                </template>
            </EmptyState>
        </div>
    </AdminLayout>
</template>
