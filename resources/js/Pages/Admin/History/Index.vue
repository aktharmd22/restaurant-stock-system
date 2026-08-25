<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, ExternalLink, Search } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import StatusText from '@/Components/ui/StatusText.vue';
import TextField from '@/Components/ui/TextField.vue';

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

const money = (value) => `${props.currency}${Number(value).toLocaleString('en-IN')}`;
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

        <Card title="Narrow it down" hint="Everything below matches what you pick here">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <div class="sm:col-span-2 xl:col-span-2">
                    <label class="mb-1.5 block text-helper text-ink-soft">Item</label>
                    <div class="relative">
                        <Search
                            :size="16"
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted"
                        />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by item name"
                            aria-label="Search by item name"
                            class="min-h-control w-full rounded-control border border-line bg-surface pl-9 pr-3 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                        />
                    </div>
                </div>

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

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <SelectField
                    class="min-w-[200px]"
                    label="Who did it"
                    :model-value="filters.who ?? ''"
                    placeholder="Anyone"
                    :options="people"
                    @update:model-value="(v) => apply({ who: v || undefined })"
                />

                <button
                    type="button"
                    class="mt-6 flex min-h-touch items-center rounded-control px-3 text-body text-primary hover:bg-primary-light"
                    @click="clearAll"
                >
                    Clear all
                </button>
            </div>
        </Card>

        <Card class="mt-4" :padded="false" :title="`${movements.total} movement${movements.total === 1 ? '' : 's'}`">
            <div v-if="movements.data.length">
                <!-- Laptop: the full picture in one row -->
                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-body">
                        <thead class="border-b border-line text-left text-helper text-ink-soft">
                            <tr>
                                <th class="px-card py-2.5 font-normal">When</th>
                                <th class="px-card py-2.5 font-normal">Item</th>
                                <th class="px-card py-2.5 font-normal">Branch</th>
                                <th class="px-card py-2.5 font-normal">What happened</th>
                                <th class="px-card py-2.5 text-right font-normal">Change</th>
                                <th class="px-card py-2.5 text-right font-normal">Left after</th>
                                <th class="px-card py-2.5 font-normal">Who</th>
                                <th class="px-card py-2.5 font-normal">Why</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="row in movements.data" :key="row.id" class="hover:bg-page">
                                <td class="whitespace-nowrap px-card py-2.5 text-ink-soft">{{ row.when_short }}</td>
                                <td class="px-card py-2.5">
                                    <Link
                                        :href="`/admin/history/item/${row.item_id}?branch=${filters.branch ?? ''}`"
                                        class="font-medium text-primary"
                                    >
                                        {{ row.item }}
                                    </Link>
                                </td>
                                <td class="px-card py-2.5 text-ink">{{ row.branch }}</td>
                                <td class="px-card py-2.5">
                                    <StatusText :status="row.tone" :label="row.what" size="sm" />
                                </td>
                                <td
                                    class="whitespace-nowrap px-card py-2.5 text-right tabular font-medium"
                                    :class="row.direction === 'in' ? 'text-approved' : 'text-rejected'"
                                >
                                    {{ row.amount }}
                                </td>
                                <td class="whitespace-nowrap px-card py-2.5 text-right tabular text-ink">
                                    {{ row.balance_after }}
                                </td>
                                <td class="whitespace-nowrap px-card py-2.5 text-ink-soft">{{ row.who }}</td>
                                <td class="px-card py-2.5">
                                    <component
                                        :is="row.why_url ? Link : 'span'"
                                        :href="row.why_url ?? undefined"
                                        class="inline-flex items-center gap-1"
                                        :class="row.why_url ? 'text-primary' : 'text-ink-soft'"
                                    >
                                        {{ row.why }}
                                        <ExternalLink v-if="row.why_url" :size="14" />
                                    </component>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Phone: the same facts, stacked -->
                <div class="divide-y divide-line lg:hidden">
                    <div v-for="row in movements.data" :key="row.id" class="p-card">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-body font-medium text-ink">{{ row.item }}</p>
                                <p class="text-helper text-ink-soft">{{ row.branch }} · {{ row.when_short }}</p>
                            </div>
                            <p
                                class="shrink-0 text-qty tabular"
                                :class="row.direction === 'in' ? 'text-approved' : 'text-rejected'"
                            >
                                {{ row.amount }}
                            </p>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <StatusText :status="row.tone" :label="row.what" size="sm" />
                            <span class="text-helper text-ink-soft">
                                left {{ row.balance_after }} · {{ row.who }}
                            </span>
                        </div>

                        <p class="mt-2 text-helper text-ink-soft">{{ row.why }}</p>
                    </div>
                </div>

                <div class="p-card">
                    <Pagination :links="movements.links" :meta="movements" />
                </div>
            </div>

            <div v-else class="p-card">
                <EmptyState
                    icon="History"
                    title="Nothing matches that"
                    message="Try a wider date range, or clear the filters."
                />
            </div>
        </Card>
    </AdminLayout>
</template>
