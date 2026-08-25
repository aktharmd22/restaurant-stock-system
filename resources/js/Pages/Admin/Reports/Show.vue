<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Download, FileText } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    report: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
    reports: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    currency: { type: String, default: '₹' },
});

const from = ref(props.filters.from ?? props.report.period.from);
const to = ref(props.filters.to ?? props.report.period.to);
const branch = ref(props.filters.branch ?? '');

const query = computed(() => ({
    from: from.value || undefined,
    to: to.value || undefined,
    branch: branch.value || undefined,
}));

function apply() {
    router.get(`/admin/reports/${props.report.key}`, query.value, { preserveState: true, preserveScroll: true, replace: true });
}

function exportUrl(format) {
    const params = new URLSearchParams(
        Object.entries(query.value).filter(([, value]) => value !== undefined),
    );

    return `/admin/reports/${props.report.key}/export/${format}?${params.toString()}`;
}

function cell(row, column) {
    const value = row[column.key];

    if (column.type === 'money') {
        return `${props.currency}${Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
    }

    return value === null || value === undefined || value === '' ? '—' : value;
}
</script>

<template>
    <AdminLayout :title="report.title">
        <Head :title="report.title" />

        <template #header-action>
            <div class="flex gap-2">
                <a
                    :href="exportUrl('xlsx')"
                    class="inline-flex min-h-touch items-center gap-2 rounded-control border border-line bg-surface px-4 text-body text-ink hover:border-primary"
                >
                    <Download :size="20" />
                    Excel
                </a>
                <a
                    :href="exportUrl('pdf')"
                    class="inline-flex min-h-touch items-center gap-2 rounded-control border border-line bg-surface px-4 text-body text-ink hover:border-primary"
                >
                    <FileText :size="20" />
                    PDF
                </a>
            </div>
        </template>

        <Link
            href="/admin/reports"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Reports
        </Link>

        <p class="mb-4 max-w-2xl text-body text-ink-soft">{{ report.hint }}</p>

        <!-- Filters -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <TextField v-model="from" label="From" type="date" />
            <TextField v-model="to" label="To" type="date" />
            <SelectField
                v-model="branch"
                label="Branch"
                placeholder="Every branch"
                :options="branches.map((b) => ({ value: b.id, label: b.name }))"
            />
            <div class="flex items-end">
                <button
                    type="button"
                    class="min-h-control w-full rounded-control bg-primary px-5 text-body font-medium text-white transition active:scale-[0.97]"
                    @click="apply"
                >
                    Show this
                </button>
            </div>
        </div>

        <!-- Summary -->
        <div v-if="Object.keys(report.totals ?? {}).length" class="mt-4 flex flex-wrap gap-3">
            <div
                v-for="(value, label) in report.totals"
                :key="label"
                class="rounded-card border border-line bg-surface px-4 py-3"
            >
                <p class="text-helper text-ink-soft">{{ label }}</p>
                <p class="text-qty tabular text-ink">{{ value }}</p>
            </div>
        </div>

        <p class="mt-4 text-helper text-ink-soft">
            {{ report.period.label }}<span v-if="report.branch"> · {{ report.branch }}</span>
        </p>

        <!-- Desktop table -->
        <div v-if="report.rows.length" class="mt-2 hidden overflow-x-auto rounded-card border border-line bg-surface lg:block">
            <table class="w-full text-body">
                <thead class="border-b border-line text-left text-helper text-ink-soft">
                    <tr>
                        <th
                            v-for="column in report.columns"
                            :key="column.key"
                            class="px-4 py-3 font-normal"
                            :class="column.align === 'right' ? 'text-right' : ''"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="(row, index) in report.rows" :key="index" :class="row.is_low ? 'bg-partial-bg/40' : ''">
                        <td
                            v-for="column in report.columns"
                            :key="column.key"
                            class="px-4 py-3 text-ink"
                            :class="column.align === 'right' ? 'text-right tabular' : ''"
                        >
                            {{ cell(row, column) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Phone: the same rows as cards -->
        <div v-if="report.rows.length" class="mt-2 space-y-2 lg:hidden">
            <SpineCard v-for="(row, index) in report.rows" :key="index" :status="row.is_low ? 'low' : null">
                <div class="p-card">
                    <p class="text-body font-medium text-ink">{{ cell(row, report.columns[0]) }}</p>
                    <dl class="mt-2 space-y-1">
                        <div
                            v-for="column in report.columns.slice(1)"
                            :key="column.key"
                            class="flex justify-between gap-3"
                        >
                            <dt class="text-helper text-ink-soft">{{ column.label }}</dt>
                            <dd class="text-body tabular text-ink">{{ cell(row, column) }}</dd>
                        </div>
                    </dl>
                </div>
            </SpineCard>
        </div>

        <EmptyState
            v-if="!report.rows.length"
            class="mt-4"
            icon="FileText"
            title="Nothing for these dates"
            message="Try a wider date range, or a different branch."
        />
    </AdminLayout>
</template>
