<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, ChevronRight, X } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import StatusText from '@/Components/ui/StatusText.vue';
import TextField from '@/Components/ui/TextField.vue';
import { statusMeta } from '@/Support/status';

const props = defineProps({
    movements: { type: Object, required: true },
    filters: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    currency: { type: String, default: '₹' },
});

const search = ref(props.filters.search ?? '');
let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => apply({ search: value || undefined }), 300);
});

const filtering = computed(() =>
    Boolean(
        search.value ||
            props.filters.branch ||
            props.filters.type ||
            props.filters.from ||
            props.filters.to ||
            props.filters.who,
    ),
);

function apply(changes = {}) {
    router.get('/admin/history', { ...props.filters, ...changes, page: undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearAll() {
    search.value = '';
    router.get('/admin/history');
}

/*
 * The whole row goes to the item's own history at that branch. It used to
 * carry two competing blue links - the item and the reason - plus a stray
 * icon floating at the right edge, so the eye had three things to consider
 * before it could read the number it came for.
 */
function rowHref(row) {
    return `/admin/history/item/${row.item_id}?branch=${props.filters.branch ?? ''}`;
}

const dot = (row) => statusMeta(row.tone).spine;
</script>

<template>
    <AdminLayout title="History" subtitle="Every movement of stock, and what caused it">
        <Head title="History" />

        <template #header-action>
            <Link
                href="/admin/history/changes"
                class="flex min-h-touch items-center gap-2 rounded-control border border-line bg-surface px-4 text-body font-medium text-ink transition hover:border-primary"
            >
                Record changes
                <ArrowRight :size="16" />
            </Link>
        </template>

        <!-- Five questions, one row of controls. -->
        <Card>
            <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                <SearchField
                    v-model="search"
                    label="Item"
                    placeholder="Search by item name"
                    class="sm:col-span-2 xl:col-span-1"
                />

                <SelectField
                    label="Branch"
                    :model-value="filters.branch ?? ''"
                    placeholder="Everywhere"
                    :options="branches"
                    @update:model-value="(v) => apply({ branch: v || undefined })"
                />

                <SelectField
                    label="What happened"
                    :model-value="filters.type ?? ''"
                    placeholder="Anything"
                    :options="types"
                    @update:model-value="(v) => apply({ type: v || undefined })"
                />

                <SelectField
                    label="Who did it"
                    :model-value="filters.who ?? ''"
                    placeholder="Anyone"
                    :options="people"
                    @update:model-value="(v) => apply({ who: v || undefined })"
                />

                <TextField
                    label="From"
                    type="date"
                    :model-value="filters.from ?? ''"
                    @update:model-value="(v) => apply({ from: v || undefined })"
                />

                <TextField
                    label="To"
                    type="date"
                    :model-value="filters.to ?? ''"
                    @update:model-value="(v) => apply({ to: v || undefined })"
                />
            </div>

            <button
                v-if="filtering"
                type="button"
                class="mt-2 inline-flex min-h-touch items-center gap-1.5 text-body text-primary"
                @click="clearAll"
            >
                <X :size="14" />
                Clear what I picked
            </button>
        </Card>

        <Card
            class="mt-4"
            :padded="false"
            :title="`${movements.total} movement${movements.total === 1 ? '' : 's'}`"
        >
            <div v-if="movements.data.length">
                <!-- One row, one destination. The numbers keep their own
                     columns so a page of them can still be read down. -->
                <div class="divide-y divide-line">
                    <Link
                        v-for="row in movements.data"
                        :key="row.id"
                        :href="rowHref(row)"
                        class="flex items-center gap-3 px-4 py-3 transition hover:bg-page sm:gap-4 sm:px-5"
                    >
                        <span
                            class="h-2 w-2 shrink-0 rounded-full"
                            :style="{ backgroundColor: dot(row) }"
                            aria-hidden="true"
                        />

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-body font-medium text-ink">{{ row.item }}</span>
                                <span class="text-helper text-ink-muted">
                                    {{ row.when_short }} · {{ row.branch }} · {{ row.who }}
                                </span>
                            </span>

                            <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <StatusText :status="row.tone" :label="row.what" size="sm" />
                                <span class="truncate text-helper text-ink-soft">{{ row.why }}</span>
                            </span>
                        </span>

                        <span class="shrink-0 text-right sm:w-24">
                            <span
                                class="block text-body tabular font-medium"
                                :class="row.direction === 'in' ? 'text-approved' : 'text-rejected'"
                            >
                                {{ row.amount }}
                            </span>
                            <span class="block text-helper tabular text-ink-muted">
                                left {{ row.balance_after }}
                            </span>
                        </span>

                        <ChevronRight :size="16" class="shrink-0 text-ink-muted" aria-hidden="true" />
                    </Link>
                </div>

                <div class="px-4 pb-4 sm:px-5">
                    <Pagination :links="movements.links" :meta="movements" />
                </div>
            </div>

            <div v-else class="p-card">
                <EmptyState
                    icon="History"
                    title="Nothing matches that"
                    message="Try a wider date range, or clear what you picked."
                />
            </div>
        </Card>
    </AdminLayout>
</template>
