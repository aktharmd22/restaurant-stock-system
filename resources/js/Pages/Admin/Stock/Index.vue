<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ClipboardCheck, TrendingDown, X } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { money } from '@/Support/money';

const props = defineProps({
    branch: { type: Object, required: true },
    branches: { type: Array, required: true },
    categories: { type: Array, required: true },
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    filters: { type: Object, required: true },
    openCount: { type: [Number, null], default: null },
});

const search = ref(props.filters.search ?? '');
const branchId = ref(props.filters.branch);
const category = ref(props.filters.category ?? '');
const show = ref(props.filters.show ?? 'all');

let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => reload({ search: value || undefined }), 300);
});

watch([branchId, category, show], () => reload());

const filtered = computed(
    () => search.value !== '' || category.value !== '' || show.value !== 'all',
);

function reload(extra = {}) {
    router.get(
        '/admin/stock',
        {
            branch: branchId.value,
            search: search.value || undefined,
            category: category.value || undefined,
            show: show.value === 'all' ? undefined : show.value,
            ...extra,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters() {
    search.value = '';
    category.value = '';
    show.value = 'all';
}

function startCount() {
    router.post('/admin/stock/count', { branch: props.branch.id });
}
</script>

<template>
    <AdminLayout title="Stock" :subtitle="`What ${branch.name} is holding right now`">
        <Head title="Stock" />

        <template #header-action>
            <AppButton
                :href="openCount ? `/admin/stock/count/${openCount}` : undefined"
                @click="openCount ? null : startCount()"
            >
                <template #icon><ClipboardCheck :size="16" /></template>
                {{ openCount ? 'Carry on counting' : 'Count stock' }}
            </AppButton>
        </template>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <StatCard label="Items" :value="totals.items" icon="Package" />
            <StatCard
                label="Running low"
                :value="totals.low"
                icon="TrendingDown"
                tone="rose"
                :hint="totals.low ? 'Below the level set for this branch' : 'Nothing to top up'"
            />
            <StatCard
                class="col-span-2 lg:col-span-2"
                label="Stock value"
                :value="money(totals.value, { decimals: 0 })"
                icon="Scale"
                tone="green"
                hint="Valued at what it cost"
            />
        </div>

        <!-- Every question this screen gets asked, in one row. -->
        <Card class="mt-4">
            <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SearchField v-model="search" placeholder="Item name" />

                <SelectField
                    v-model="branchId"
                    label="Branch"
                    :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                />

                <SelectField
                    v-model="category"
                    label="Group"
                    placeholder="Every group"
                    :options="categories.map((c) => ({ value: c.id, label: c.name }))"
                />

                <SelectField
                    v-model="show"
                    label="Show"
                    :options="[
                        { value: 'all', label: 'Everything' },
                        { value: 'low', label: 'Running low only' },
                        { value: 'none', label: 'Nothing left only' },
                    ]"
                />
            </div>

            <button
                v-if="filtered"
                type="button"
                class="mt-2 inline-flex min-h-touch items-center gap-1.5 text-body text-primary"
                @click="clearFilters"
            >
                <X :size="14" />
                Clear what I picked
            </button>
        </Card>

        <!-- Desktop: a table, because columns of numbers are the point here -->
        <div
            v-if="rows.length"
            class="mt-4 hidden overflow-x-auto rounded-card border border-line bg-surface shadow-card lg:block"
        >
            <table class="w-full text-body">
                <thead class="border-b border-line text-left text-helper text-ink-soft">
                    <tr>
                        <th class="px-4 py-3 font-normal">Item</th>
                        <th class="whitespace-nowrap px-4 py-3 font-normal">Where</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right font-normal">On hand</th>
                        <th v-if="branch.is_main" class="whitespace-nowrap px-4 py-3 text-right font-normal">
                            Set aside
                        </th>
                        <th v-if="branch.is_main" class="whitespace-nowrap px-4 py-3 text-right font-normal">
                            Free
                        </th>
                        <th class="whitespace-nowrap px-4 py-3 text-right font-normal">Full shelf</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right font-normal">Use by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="row in rows" :key="row.id" class="transition hover:bg-page">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2 text-ink">
                                <TrendingDown
                                    v-if="row.is_low"
                                    :size="14"
                                    class="shrink-0 text-partial"
                                    aria-hidden="true"
                                />
                                {{ row.name }}
                            </span>
                            <span class="block text-helper text-ink-soft">
                                {{ row.category }}
                                <span v-if="row.is_low" class="font-medium text-partial">· running low</span>
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-helper text-ink-soft">
                            {{ row.storage_location ?? '—' }}
                        </td>
                        <td
                            class="whitespace-nowrap px-4 py-3 text-right tabular"
                            :class="row.is_negative ? 'text-rejected' : row.is_low ? 'text-partial' : 'text-ink'"
                        >
                            {{ row.on_hand_text }}
                        </td>
                        <td
                            v-if="branch.is_main"
                            class="whitespace-nowrap px-4 py-3 text-right tabular text-ink-soft"
                        >
                            {{ row.reserved_text }}
                        </td>
                        <td v-if="branch.is_main" class="whitespace-nowrap px-4 py-3 text-right tabular text-ink">
                            {{ row.available_text }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular text-ink-soft">
                            {{ row.par_text ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular text-ink-soft">
                            {{ row.use_by ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Phone: the same numbers, one list. Never a sideways scroll. -->
        <div v-if="rows.length" class="mt-4 lg:hidden">
            <Card :padded="false">
                <div class="divide-y divide-line">
                    <div v-for="row in rows" :key="row.id" class="px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-body font-medium text-ink">{{ row.name }}</p>
                                <p class="truncate text-helper text-ink-soft">{{ row.category }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p
                                    class="text-qty tabular"
                                    :class="row.is_low ? 'text-partial' : 'text-ink'"
                                >
                                    {{ row.on_hand_text }}
                                </p>
                                <p v-if="row.is_low" class="text-micro font-medium text-partial">low</p>
                            </div>
                        </div>

                        <dl class="mt-1 flex flex-wrap gap-x-4 text-helper text-ink-soft">
                            <div v-if="branch.is_main" class="flex gap-1">
                                <dt>Set aside</dt>
                                <dd class="tabular text-ink">{{ row.reserved_text }}</dd>
                            </div>
                            <div v-if="branch.is_main" class="flex gap-1">
                                <dt>Free</dt>
                                <dd class="tabular text-ink">{{ row.available_text }}</dd>
                            </div>
                            <div class="flex gap-1">
                                <dt>Full shelf</dt>
                                <dd class="tabular text-ink">{{ row.par_text ?? '—' }}</dd>
                            </div>
                            <div v-if="row.use_by" class="flex gap-1">
                                <dt>Use by</dt>
                                <dd class="tabular text-ink">{{ row.use_by }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </Card>
        </div>

        <EmptyState
            v-if="!rows.length"
            class="mt-4"
            icon="Boxes"
            :title="filtered ? 'Nothing matches that' : 'Nothing here yet'"
            :message="
                filtered
                    ? 'Try a different word, or clear what you picked.'
                    : 'Record a purchase or send some stock to a branch to see numbers.'
            "
        >
            <template v-if="filtered" #action>
                <AppButton variant="secondary" @click="clearFilters">Clear what I picked</AppButton>
            </template>
        </EmptyState>
    </AdminLayout>
</template>
