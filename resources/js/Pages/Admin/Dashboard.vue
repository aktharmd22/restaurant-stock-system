<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BarChart from '@/Components/ui/BarChart.vue';
import Card from '@/Components/ui/Card.vue';
import DonutChart from '@/Components/ui/DonutChart.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    months: { type: Object, required: true },
    statusMix: { type: Array, default: () => [] },
    stockSummary: { type: Array, default: () => [] },
    needsAction: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    currency: { type: String, default: '₹' },
});

const money = (value) =>
    `${props.currency}${Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const series = computed(() => [
    { name: 'Sent to branches', color: '#1E3A8A', values: props.months.sent_out },
    { name: 'Bought in', color: '#A9BCE8', values: props.months.bought_in },
]);

// "₹6000k" is not how anyone here reads a number.
const compactMoney = (value) => {
    const c = props.currency;

    const trim = (n) => n.toFixed(1).replace(/\.0$/, '');

    if (value >= 10000000) return `${c}${trim(value / 10000000)}cr`;
    if (value >= 100000) return `${c}${trim(value / 100000)}L`;
    if (value >= 1000) return `${c}${Math.round(value / 1000)}k`;
    return `${c}${Math.round(value)}`;
};

const tiles = {
    blue: 'bg-tile-blue text-tile-blue-ink',
    violet: 'bg-tile-violet text-tile-violet-ink',
    rose: 'bg-tile-rose text-tile-rose-ink',
    amber: 'bg-tile-amber text-tile-amber-ink',
};
</script>

<template>
    <AdminLayout title="Dashboard" subtitle="Everything that needs you, in one place">
        <Head title="Dashboard" />

        <template #header-action>
            <AppButton href="/admin/requests">
                Open requests
                <template #icon><ArrowRight :size="16" /></template>
            </AppButton>
        </template>

        <!-- Every tile goes straight to the list behind it -->
        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4 xl:gap-4">
            <StatCard
                label="Waiting for you"
                :value="stats.waiting"
                icon="Clock"
                tone="amber"
                href="/admin/requests?status=waiting"
                hint="Branches are waiting on these"
            />
            <StatCard
                label="To send today"
                :value="stats.to_send"
                icon="Truck"
                tone="blue"
                href="/admin/dispatch"
                hint="Approved and ready to pack"
            />
            <StatCard
                label="Stock at the main store"
                :value="money(stats.stock_value)"
                icon="Boxes"
                tone="violet"
                href="/admin/stock"
                hint="Valued at what it cost"
            />
            <StatCard
                label="Bought this month"
                :value="money(stats.spent_this_month)"
                icon="ShoppingCart"
                tone="green"
                href="/admin/purchase"
                :delta="stats.spent_change"
                :hint="stats.spent_change === null ? 'No month before this to compare' : null"
                lower-is-better
            />
        </div>

        <!-- Money in, money out, and where this month's requests went -->
        <div class="mt-4 grid gap-4 xl:grid-cols-3">
            <Card
                class="xl:col-span-2"
                title="Sent out and bought in"
                hint="Last six months, valued at cost"
            >
                <BarChart
                    :series="series"
                    :categories="months.labels"
                    :format="compactMoney"
                />
            </Card>

            <Card title="This month's requests" hint="Where they ended up">
                <DonutChart
                    :slices="statusMix"
                    centre-label="requests"
                    :centre-value="statusMix.reduce((sum, s) => sum + s.value, 0)"
                />
            </Card>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-3">
            <!-- The queue -->
            <Card class="xl:col-span-2" title="Needs you now" :padded="false">
                <template #action>
                    <Link
                        href="/admin/requests?status=waiting"
                        class="flex min-h-touch items-center gap-1 text-body font-medium text-primary"
                    >
                        See all
                        <ArrowRight :size="16" />
                    </Link>
                </template>

                <div v-if="needsAction.length" class="divide-y divide-line">
                    <Link
                        v-for="request in needsAction"
                        :key="request.id"
                        :href="`/admin/requests?selected=${request.id}`"
                        class="flex flex-wrap items-center gap-3 px-card py-3 transition hover:bg-page"
                    >
                        <span
                            class="h-8 w-1 shrink-0 rounded-full"
                            :style="{ backgroundColor: request.is_late ? '#C2410C' : '#B45309' }"
                            aria-hidden="true"
                        />

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-body font-medium text-ink">
                                {{ request.branch }}
                            </span>
                            <span class="block truncate text-helper text-ink-soft">
                                {{ request.number }} · {{ request.item_count }} items ·
                                {{ request.sent_at_text }}
                            </span>
                        </span>

                        <StatusPill v-if="request.is_late" status="late" />
                        <StatusPill :status="request.status" />
                    </Link>
                </div>

                <div v-else class="p-card">
                    <EmptyState
                        icon="Inbox"
                        title="Nothing needs you"
                        message="New requests from branches land here."
                    />
                </div>
            </Card>

            <!-- Stock at a glance -->
            <Card title="Stock right now" hint="Across the whole business">
                <ul class="space-y-2.5">
                    <li
                        v-for="row in stockSummary"
                        :key="row.label"
                        class="flex items-center justify-between gap-3 rounded-control bg-page px-3 py-2.5"
                    >
                        <span class="min-w-0 text-helper text-ink-soft">{{ row.label }}</span>
                        <span
                            class="shrink-0 rounded-control px-2 py-0.5 text-body font-semibold tabular"
                            :class="tiles[row.tone]"
                        >
                            {{ row.value }}
                        </span>
                    </li>
                </ul>

                <Link
                    href="/admin/stock"
                    class="mt-3 flex min-h-touch items-center justify-center gap-1 rounded-control border border-line text-body font-medium text-primary transition hover:bg-page"
                >
                    Open stock
                    <ArrowRight :size="16" />
                </Link>
            </Card>
        </div>

        <!-- Everything recent, as a table on a laptop -->
        <Card class="mt-4" title="Latest requests" :padded="false">
            <template #action>
                <Link
                    href="/admin/requests?status=all"
                    class="flex min-h-touch items-center gap-1 text-body font-medium text-primary"
                >
                    See all
                    <ArrowRight :size="16" />
                </Link>
            </template>

            <div v-if="recent.length" class="hidden overflow-x-auto lg:block">
                <table class="w-full text-body">
                    <thead class="border-b border-line text-left text-helper text-ink-soft">
                        <tr>
                            <th class="px-card py-2.5 font-normal">Request</th>
                            <th class="px-card py-2.5 font-normal">Branch</th>
                            <th class="px-card py-2.5 font-normal">Sent</th>
                            <th class="px-card py-2.5 text-right font-normal">Items</th>
                            <th class="px-card py-2.5 text-right font-normal">Where it is</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="request in recent" :key="request.id" class="transition hover:bg-page">
                            <td class="px-card py-2.5">
                                <Link
                                    :href="`/admin/requests?status=all&selected=${request.id}`"
                                    class="font-medium text-primary"
                                >
                                    {{ request.number }}
                                </Link>
                            </td>
                            <td class="px-card py-2.5 text-ink">{{ request.branch }}</td>
                            <td class="px-card py-2.5 text-ink-soft">{{ request.sent_at_text }}</td>
                            <td class="px-card py-2.5 text-right tabular text-ink">
                                {{ request.item_count }}
                            </td>
                            <td class="px-card py-2.5 text-right">
                                <StatusPill :status="request.status" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Phone: the same rows, stacked -->
            <div v-if="recent.length" class="divide-y divide-line lg:hidden">
                <Link
                    v-for="request in recent"
                    :key="request.id"
                    :href="`/admin/requests?status=all&selected=${request.id}`"
                    class="flex items-center gap-3 px-card py-3"
                >
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-body font-medium text-ink">
                            {{ request.branch }}
                        </span>
                        <span class="block truncate text-helper text-ink-soft">
                            {{ request.number }} · {{ request.item_count }} items ·
                            {{ request.sent_at_text }}
                        </span>
                    </span>
                    <StatusPill :status="request.status" />
                </Link>
            </div>

            <div v-if="!recent.length" class="p-card">
                <EmptyState
                    icon="ClipboardList"
                    title="No requests yet"
                    message="Once a branch asks for stock, it shows up here."
                />
            </div>
        </Card>
    </AdminLayout>
</template>
