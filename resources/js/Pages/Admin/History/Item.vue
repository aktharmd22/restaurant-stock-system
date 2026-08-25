<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';

const props = defineProps({
    item: { type: Object, required: true },
    branch: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
    movements: { type: Array, default: () => [] },
    currency: { type: String, default: '₹' },
});

function pickBranch(id) {
    router.get(`/admin/history/item/${props.item.id}`, { branch: id }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <AdminLayout :title="item.name" :subtitle="`Everything that has happened to it at ${branch.name}`">
        <Head :title="`${item.name} history`" />

        <template #header-action>
            <Link
                href="/admin/history"
                class="flex min-h-touch items-center gap-2 rounded-control border border-line bg-surface px-4 text-body font-medium text-ink transition hover:border-primary"
            >
                <ArrowLeft :size="16" />
                All history
            </Link>
        </template>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 xl:gap-4">
            <StatCard label="On the shelf" :value="item.on_hand" icon="Boxes" tone="blue" />
            <StatCard label="Set aside" :value="item.reserved" icon="Clock" tone="amber" />
            <StatCard label="Free to promise" :value="item.available" icon="Check" tone="green" />
            <div class="rounded-card border border-line bg-surface p-card shadow-card">
                <SelectField
                    label="Branch"
                    :model-value="branch.id"
                    :options="branches"
                    @update:model-value="pickBranch"
                />
                <p class="mt-2 text-helper text-ink-soft">
                    Worth {{ currency }}{{ item.avg_cost.toFixed(4) }} per smallest unit.
                </p>
            </div>
        </div>

        <Card
            class="mt-4"
            :padded="false"
            title="Every movement"
            hint="Newest first. The running balance is what was left straight after each one."
        >
            <div v-if="movements.length" class="divide-y divide-line">
                <div
                    v-for="row in movements"
                    :key="row.id"
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 p-card"
                >
                    <p class="w-40 shrink-0 text-helper text-ink-soft">{{ row.when }}</p>

                    <StatusPill :status="row.tone" :label="row.what" />

                    <p
                        class="w-24 shrink-0 text-right text-body tabular font-medium"
                        :class="row.direction === 'in' ? 'text-approved' : 'text-rejected'"
                    >
                        {{ row.amount }}
                    </p>

                    <p class="w-28 shrink-0 text-right text-body tabular text-ink">
                        {{ row.balance_after }}
                    </p>

                    <p class="min-w-[200px] flex-1 text-helper">
                        <component
                            :is="row.why_url ? Link : 'span'"
                            :href="row.why_url ?? undefined"
                            class="inline-flex items-center gap-1"
                            :class="row.why_url ? 'text-primary' : 'text-ink-soft'"
                        >
                            {{ row.why }}
                            <ExternalLink v-if="row.why_url" :size="14" />
                        </component>
                        <span class="text-ink-muted"> · {{ row.who }}</span>
                    </p>
                </div>
            </div>

            <div v-else class="p-card">
                <EmptyState
                    icon="History"
                    title="Nothing has happened yet"
                    message="This item has never moved at this branch."
                />
            </div>
        </Card>
    </AdminLayout>
</template>
