<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Lightbulb, Plus, Users } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Card from '@/Components/ui/Card.vue';
import ListRow from '@/Components/ui/ListRow.vue';
import StatusText from '@/Components/ui/StatusText.vue';
import { rupees } from '@/Support/money';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    currency: { type: String, default: '₹' },
});

const tabs = [
    { value: 'open', label: 'Still coming' },
    { value: 'done', label: 'Finished' },
    { value: 'all', label: 'Everything' },
];

function pick(status) {
    router.get('/admin/purchase', { status }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <AdminLayout title="Purchase">
        <Head title="Purchase" />

        <template #header-action>
            <AppButton href="/admin/purchase/new">
                <template #icon><Plus :size="20" /></template>
                New order
            </AppButton>
        </template>

        <div class="mb-4 flex flex-wrap gap-2">
            <Link
                href="/admin/purchase/suggestions"
                class="inline-flex min-h-touch items-center gap-2 rounded-control border border-line bg-surface px-4 text-body text-ink hover:border-primary"
            >
                <Lightbulb :size="20" class="text-primary" />
                What to buy
            </Link>
            <Link
                href="/admin/suppliers"
                class="inline-flex min-h-touch items-center gap-2 rounded-control border border-line bg-surface px-4 text-body text-ink hover:border-primary"
            >
                <Users :size="20" class="text-primary" />
                Suppliers
            </Link>
        </div>

        <div class="mb-4 flex gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                class="min-h-touch rounded-full border px-4 text-body transition"
                :class="
                    filters.status === tab.value
                        ? 'border-primary bg-primary-light font-medium text-primary'
                        : 'border-line bg-surface text-ink-soft'
                "
                @click="pick(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="orders.data.length">
            <Card :padded="false">
                <div class="divide-y divide-line">
                    <ListRow
                        v-for="order in orders.data"
                        :key="order.id"
                        :href="`/admin/purchase/${order.id}`"
                        :status="order.tone"
                    >
                        <span class="block truncate text-body font-medium text-ink">
                            {{ order.supplier }}
                        </span>
                        <span class="mt-0.5 block truncate text-helper text-ink-soft">
                            {{ order.number }} · {{ order.lines }} items · {{ order.branch }}
                        </span>
                        <span class="block truncate text-helper text-ink-muted">
                            Placed {{ order.placed }}
                            <span v-if="order.expected"> · due {{ order.expected }}</span>
                        </span>

                        <template #end>
                            <span class="text-right text-body tabular text-ink sm:w-24">
                                {{ rupees(order.total, currency) }}
                            </span>
                            <span class="hidden w-[132px] justify-end sm:flex">
                                <StatusText :status="order.tone" :label="order.status_label" size="sm" />
                            </span>
                        </template>
                    </ListRow>
                </div>
            </Card>

            <Pagination :links="orders.links" :meta="orders" />
        </div>

        <EmptyState
            v-else
            icon="ShoppingCart"
            title="No orders here"
            message="Place an order and it will show up here until everything has arrived."
        >
            <template #action>
                <AppButton href="/admin/purchase/new">New order</AppButton>
            </template>
        </EmptyState>
    </AdminLayout>
</template>
