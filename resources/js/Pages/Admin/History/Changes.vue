<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    changes: { type: Object, required: true },
    filters: { type: Object, required: true },
    people: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
});

// Plain words for what happened to a record.
const events = { created: 'Added', updated: 'Changed', deleted: 'Removed' };

const records = {
    Item: 'Item',
    BranchItemSetting: 'Par level',
    Branch: 'Branch',
    User: 'Person',
    Supplier: 'Supplier',
    Category: 'Item group',
    StockRequest: 'Request',
};

function apply(changes = {}) {
    router.get('/admin/history/changes', { ...props.filters, ...changes, page: undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <AdminLayout title="Record changes" subtitle="Who changed what, and what it was before">
        <Head title="Record changes" />

        <template #header-action>
            <Link
                href="/admin/history"
                class="flex min-h-touch items-center gap-2 rounded-control border border-line bg-surface px-4 text-body font-medium text-ink transition hover:border-primary"
            >
                <ArrowLeft :size="16" />
                Stock movements
            </Link>
        </template>

        <Card title="Narrow it down">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SelectField
                    label="Kind of record"
                    :model-value="filters.subject ?? ''"
                    :options="subjects"
                    @update:model-value="(v) => apply({ subject: v || undefined })"
                />
                <SelectField
                    label="Who changed it"
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
        </Card>

        <Card
            class="mt-4"
            :padded="false"
            :title="`${changes.total} change${changes.total === 1 ? '' : 's'}`"
        >
            <div v-if="changes.data.length" class="divide-y divide-line">
                <div v-for="row in changes.data" :key="row.id" class="p-card">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-body text-ink">
                            <span class="font-medium">{{ row.who }}</span>
                            {{ (events[row.event] ?? row.event ?? 'changed').toLowerCase() }}
                            a {{ (records[row.what] ?? row.what).toLowerCase() }}
                        </p>
                        <p class="text-helper text-ink-soft">{{ row.when }}</p>
                    </div>

                    <!-- Before and after, side by side, so nothing has to be guessed -->
                    <ul v-if="row.fields.length" class="mt-2.5 space-y-1.5">
                        <li
                            v-for="field in row.fields"
                            :key="field.field"
                            class="flex flex-wrap items-center gap-2 rounded-control bg-page px-3 py-2 text-helper"
                        >
                            <span class="text-ink-soft">{{ field.field }}</span>
                            <span class="tabular text-ink-muted line-through">{{ field.from }}</span>
                            <ArrowRight :size="14" class="text-ink-muted" aria-hidden="true" />
                            <span class="tabular font-medium text-ink">{{ field.to }}</span>
                        </li>
                    </ul>

                    <p v-else class="mt-2 text-helper text-ink-soft">
                        No field changed - the record was {{ (events[row.event] ?? 'touched').toLowerCase() }}.
                    </p>
                </div>

                <div class="p-card">
                    <Pagination :links="changes.links" :meta="changes" />
                </div>
            </div>

            <div v-else class="p-card">
                <EmptyState
                    icon="History"
                    title="No changes recorded"
                    message="Edits to items, par levels, branches and people show up here."
                />
            </div>
        </Card>
    </AdminLayout>
</template>
