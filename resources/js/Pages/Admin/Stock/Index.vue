<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ClipboardCheck, Clock, Pencil, Plus, Scale, Trash2, TrendingDown, X } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import RowMenu from '@/Components/ui/RowMenu.vue';
import RowMenuItem from '@/Components/ui/RowMenuItem.vue';
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
    canAdjust: { type: Boolean, default: false },
    canAddItems: { type: Boolean, default: false },
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

/*
 * Correcting a number.
 *
 * You cannot edit a stock balance, and you should not want to: the number is
 * the sum of everything that ever happened to the item. So the question the
 * sheet asks is "what is actually on the shelf", and the app writes the
 * difference to the ledger with the reason attached.
 */
const correcting = ref(null);

const correction = useForm({ branch_id: null, item_id: null, counted: 0, reason: '' });

const REASONS = [
    'Counted it and this is what is there',
    'Found more than the app said',
    'Damaged, thrown out earlier',
    'Someone typed the wrong number before',
    'Spilled or lost',
];

const difference = computed(() => {
    if (!correcting.value) return 0;

    return Math.round((correction.counted - correcting.value.on_hand) * 100) / 100;
});

function openCorrection(row) {
    correcting.value = row;
    correction.clearErrors();
    correction.branch_id = props.branch.id;
    correction.item_id = row.id;
    correction.counted = row.on_hand;
    correction.reason = '';
}

function saveCorrection() {
    correction.post('/admin/stock/correct', {
        preserveScroll: true,
        onSuccess: () => {
            correcting.value = null;
        },
    });
}
</script>

<template>
    <AdminLayout title="Stock" :subtitle="`What ${branch.name} is holding right now`">
        <Head title="Stock" />

        <template #header-action>
            <div class="flex gap-2">
                <!-- Stock is where someone notices an item is missing, so this
                     is where they should be able to add one. -->
                <AppButton
                    v-if="canAddItems"
                    variant="secondary"
                    href="/admin/settings/items/new"
                >
                    <template #icon><Plus :size="16" /></template>
                    Add item
                </AppButton>

                <AppButton
                    :href="openCount ? `/admin/stock/count/${openCount}` : undefined"
                    @click="openCount ? null : startCount()"
                >
                    <template #icon><ClipboardCheck :size="16" /></template>
                    {{ openCount ? 'Carry on counting' : 'Count stock' }}
                </AppButton>
            </div>
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
                        <th class="px-4 py-3" style="width: 56px"><span class="sr-only">Actions</span></th>
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
                        <td class="px-2 py-1">
                            <RowMenu :label="`More for ${row.name}`">
                                <RowMenuItem v-if="canAdjust" @click="openCorrection(row)">
                                    <template #icon><Scale :size="16" /></template>
                                    Correct the number
                                </RowMenuItem>
                                <RowMenuItem :href="`/waste?item=${row.id}&branch=${branch.id}`">
                                    <template #icon><Trash2 :size="16" /></template>
                                    Throw some away
                                </RowMenuItem>
                                <RowMenuItem :href="`/admin/history/item/${row.id}?branch=${branch.id}`">
                                    <template #icon><Clock :size="16" /></template>
                                    See its history
                                </RowMenuItem>
                                <RowMenuItem :href="`/admin/settings/items/${row.id}/edit`">
                                    <template #icon><Pencil :size="16" /></template>
                                    Edit the item
                                </RowMenuItem>
                            </RowMenu>
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
                            <div class="flex shrink-0 items-start gap-1">
                                <div class="text-right">
                                    <p
                                        class="text-qty tabular"
                                        :class="row.is_low ? 'text-partial' : 'text-ink'"
                                    >
                                        {{ row.on_hand_text }}
                                    </p>
                                    <p v-if="row.is_low" class="text-micro font-medium text-partial">low</p>
                                </div>

                                <RowMenu :label="`More for ${row.name}`">
                                    <RowMenuItem v-if="canAdjust" @click="openCorrection(row)">
                                        <template #icon><Scale :size="16" /></template>
                                        Correct the number
                                    </RowMenuItem>
                                    <RowMenuItem :href="`/waste?item=${row.id}&branch=${branch.id}`">
                                        <template #icon><Trash2 :size="16" /></template>
                                        Throw some away
                                    </RowMenuItem>
                                    <RowMenuItem :href="`/admin/history/item/${row.id}?branch=${branch.id}`">
                                        <template #icon><Clock :size="16" /></template>
                                        See its history
                                    </RowMenuItem>
                                    <RowMenuItem :href="`/admin/settings/items/${row.id}/edit`">
                                        <template #icon><Pencil :size="16" /></template>
                                        Edit the item
                                    </RowMenuItem>
                                </RowMenu>
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
            <template #action>
                <div class="flex flex-wrap justify-center gap-2">
                    <AppButton v-if="filtered" variant="secondary" @click="clearFilters">
                        Clear what I picked
                    </AppButton>
                    <AppButton
                        v-if="canAddItems && search"
                        :href="`/admin/settings/items/new?name=${encodeURIComponent(search)}`"
                    >
                        Add "{{ search }}" as a new item
                    </AppButton>
                    <AppButton v-else-if="canAddItems" href="/admin/settings/items/new">
                        Add an item
                    </AppButton>
                </div>
            </template>
        </EmptyState>

        <BottomSheet
            :open="correcting !== null"
            :title="`Correct ${correcting?.name}`"
            description="Say what is actually on the shelf. The app works out the difference and writes it down with your reason."
            @close="correcting = null"
        >
            <div v-if="correcting" class="space-y-4">
                <div class="flex items-center justify-between gap-3 rounded-control bg-page px-4 py-3">
                    <span class="text-body text-ink-soft">The app thinks</span>
                    <span class="text-qty tabular text-ink">{{ correcting.on_hand_text }}</span>
                </div>

                <div>
                    <p class="mb-2 text-helper text-ink-soft">What is actually there</p>
                    <QtyStepper
                        v-model="correction.counted"
                        :step="correcting.step ?? 1"
                        :decimals="correcting.decimals ?? 2"
                        :unit="correcting.unit"
                        :label="correcting.name"
                    />
                    <p v-if="correction.errors.counted" class="mt-2 text-helper text-rejected">
                        {{ correction.errors.counted }}
                    </p>
                </div>

                <!-- The difference, said out loud before it is saved. -->
                <p v-if="difference !== 0" class="text-body" :class="difference > 0 ? 'text-approved' : 'text-rejected'">
                    That {{ difference > 0 ? 'adds' : 'takes off' }}
                    <span class="tabular font-medium">
                        {{ Math.abs(difference) }} {{ correcting.unit }}
                    </span>.
                </p>
                <p v-else class="text-body text-ink-soft">That is the same number. Nothing will change.</p>

                <div>
                    <p class="mb-2 text-helper text-ink-soft">Why</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="reason in REASONS"
                            :key="reason"
                            type="button"
                            class="min-h-touch rounded-full border px-4 text-body transition"
                            :class="
                                correction.reason === reason
                                    ? 'border-primary bg-primary-light font-medium text-primary'
                                    : 'border-line bg-surface text-ink-soft hover:text-ink'
                            "
                            @click="correction.reason = reason"
                        >
                            {{ reason }}
                        </button>
                    </div>
                    <p v-if="correction.errors.reason" class="mt-2 text-helper text-rejected">
                        {{ correction.errors.reason }}
                    </p>
                </div>
            </div>

            <template #footer>
                <AppButton
                    block
                    size="lg"
                    :disabled="!correction.reason || difference === 0"
                    :loading="correction.processing"
                    loading-text="Writing it down…"
                    @click="saveCorrection"
                >
                    Save the correction
                </AppButton>
            </template>
        </BottomSheet>
    </AdminLayout>
</template>
