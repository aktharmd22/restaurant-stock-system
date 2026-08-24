<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ClipboardCheck, Search, TrendingDown } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import StatCard from '@/Components/ui/StatCard.vue';

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
let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => reload({ search: value || undefined }), 300);
});

function reload(changes = {}) {
    router.get('/admin/stock', { ...props.filters, ...changes }, { preserveState: true, replace: true });
}

function startCount() {
    router.post('/admin/stock/count', { branch: props.branch.id });
}
</script>

<template>
    <AdminLayout title="Stock">
        <Head title="Stock" />

        <template #header-action>
            <AppButton :href="openCount ? `/admin/stock/count/${openCount}` : undefined" @click="openCount ? null : startCount()">
                <template #icon><ClipboardCheck :size="20" /></template>
                {{ openCount ? 'Carry on counting' : 'Count stock' }}
            </AppButton>
        </template>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <StatCard label="Items" :value="totals.items" icon="Package" />
            <StatCard label="Running low" :value="totals.low" icon="TrendingDown" tone="partial" />
            <StatCard label="Stock value" :value="`₹${totals.value.toLocaleString('en-IN')}`" icon="Scale" />
            <div class="col-span-2 lg:col-span-1">
                <SelectField
                    label="Branch"
                    :model-value="branch.id"
                    :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                    @update:model-value="(value) => reload({ branch: value })"
                />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <div class="relative min-w-[220px] flex-1">
                <Search :size="20" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-muted" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search items"
                    aria-label="Search items"
                    class="min-h-control w-full rounded-control border border-line bg-surface pl-12 pr-4 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                />
            </div>

            <div class="no-scrollbar flex gap-2 overflow-x-auto">
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
        </div>

        <!-- Desktop: a table, because columns of numbers are the point here -->
        <div v-if="rows.length" class="mt-4 hidden overflow-x-auto rounded-card border border-line bg-surface lg:block">
            <table class="w-full text-body">
                <thead class="border-b border-line text-left text-helper text-ink-soft">
                    <tr>
                        <th class="px-4 py-3 font-normal">Item</th>
                        <th class="px-4 py-3 font-normal">Where</th>
                        <th class="px-4 py-3 text-right font-normal">On hand</th>
                        <th v-if="branch.is_main" class="px-4 py-3 text-right font-normal">Set aside</th>
                        <th v-if="branch.is_main" class="px-4 py-3 text-right font-normal">Free</th>
                        <th class="px-4 py-3 text-right font-normal">Full shelf</th>
                        <th class="px-4 py-3 text-right font-normal">Use by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="row in rows" :key="row.id" :class="row.is_low ? 'bg-partial-bg/40' : ''">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2 text-ink">
                                <TrendingDown v-if="row.is_low" :size="16" class="text-partial" aria-hidden="true" />
                                {{ row.name }}
                            </span>
                            <span class="block text-helper text-ink-soft">{{ row.category }}</span>
                        </td>
                        <td class="px-4 py-3 text-helper text-ink-soft">{{ row.storage_location ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular" :class="row.is_negative ? 'text-rejected' : 'text-ink'">
                            {{ row.on_hand_text }}
                        </td>
                        <td v-if="branch.is_main" class="px-4 py-3 text-right tabular text-ink-soft">
                            {{ row.reserved_text }}
                        </td>
                        <td v-if="branch.is_main" class="px-4 py-3 text-right tabular text-ink">
                            {{ row.available_text }}
                        </td>
                        <td class="px-4 py-3 text-right tabular text-ink-soft">{{ row.par_text ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular text-ink-soft">{{ row.use_by ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Phone: the same numbers as cards. Never a sideways scroll. -->
        <div v-if="rows.length" class="mt-4 space-y-2 lg:hidden">
            <SpineCard v-for="row in rows" :key="row.id" :status="row.is_low ? 'low' : 'approved'">
                <div class="p-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-body font-medium text-ink">{{ row.name }}</p>
                            <p class="text-helper text-ink-soft">{{ row.category }}</p>
                        </div>
                        <p class="shrink-0 text-qty tabular" :class="row.is_low ? 'text-partial' : 'text-ink'">
                            {{ row.on_hand_text }}
                        </p>
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div v-if="branch.is_main" class="rounded-control bg-page py-2">
                            <dt class="text-helper text-ink-soft">Set aside</dt>
                            <dd class="text-body tabular text-ink">{{ row.reserved_text }}</dd>
                        </div>
                        <div v-if="branch.is_main" class="rounded-control bg-page py-2">
                            <dt class="text-helper text-ink-soft">Free</dt>
                            <dd class="text-body tabular text-ink">{{ row.available_text }}</dd>
                        </div>
                        <div class="rounded-control bg-page py-2">
                            <dt class="text-helper text-ink-soft">Full shelf</dt>
                            <dd class="text-body tabular text-ink">{{ row.par_text ?? '—' }}</dd>
                        </div>
                        <div v-if="row.use_by" class="rounded-control bg-page py-2">
                            <dt class="text-helper text-ink-soft">Use by</dt>
                            <dd class="text-body tabular text-ink">{{ row.use_by }}</dd>
                        </div>
                    </dl>
                </div>
            </SpineCard>
        </div>

        <EmptyState
            v-else
            class="mt-4"
            icon="Boxes"
            title="Nothing here yet"
            message="Record a purchase or send some stock to a branch to see numbers."
        />
    </AdminLayout>
</template>
