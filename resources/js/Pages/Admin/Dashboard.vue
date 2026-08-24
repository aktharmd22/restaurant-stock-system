<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';

defineProps({
    stats: { type: Object, required: true },
    needsAction: { type: Array, default: () => [] },
    inTransit: { type: Array, default: () => [] },
});
</script>

<template>
    <AdminLayout title="Dashboard">
        <Head title="Dashboard" />

        <!-- Every tile goes straight to the list behind it -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <StatCard
                label="Waiting for you"
                :value="stats.waiting"
                icon="Clock"
                tone="waiting"
                href="/admin/requests?status=waiting"
            />
            <StatCard
                label="To send today"
                :value="stats.to_send"
                icon="Truck"
                tone="primary"
                href="/admin/dispatch"
            />
            <StatCard
                label="In transit"
                :value="stats.in_transit"
                icon="MapPin"
                hint="Left the store, not confirmed yet"
                href="/admin/requests?status=sent"
            />
            <StatCard
                label="Low stock items"
                :value="stats.low_stock"
                icon="TrendingDown"
                tone="partial"
                href="/admin/stock"
            />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <h2 class="mb-3 text-heading text-ink">Needs you now</h2>

                <div v-if="needsAction.length" class="space-y-2">
                    <SpineCard
                        v-for="request in needsAction"
                        :key="request.id"
                        :status="request.is_late ? 'late' : request.status"
                    >
                        <Link :href="`/admin/requests?selected=${request.id}`" class="block p-card">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-body font-medium text-ink">
                                        {{ request.branch }}
                                        <span class="text-ink-soft">· {{ request.item_count }} items</span>
                                    </p>
                                    <p class="mt-0.5 text-helper text-ink-soft">
                                        {{ request.number }} · {{ request.sent_at_text }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <StatusPill v-if="request.is_late" status="late" />
                                    <StatusPill :status="request.status" />
                                    <ChevronRight :size="20" class="text-ink-muted" />
                                </div>
                            </div>
                        </Link>
                    </SpineCard>
                </div>

                <EmptyState
                    v-else
                    icon="Inbox"
                    title="Nothing needs you right now"
                    message="New requests from branches will appear here."
                />
            </section>

            <section>
                <h2 class="mb-3 text-heading text-ink">On the way</h2>

                <div v-if="inTransit.length" class="space-y-2">
                    <SpineCard v-for="request in inTransit" :key="request.id" status="sent">
                        <div class="p-card">
                            <p class="text-body font-medium text-ink">{{ request.branch }}</p>
                            <p class="text-helper text-ink-soft">
                                {{ request.item_count }} items · {{ request.number }}
                            </p>
                        </div>
                    </SpineCard>
                </div>

                <p v-else class="rounded-card border border-line bg-surface p-card text-body text-ink-soft">
                    Nothing is out for delivery.
                </p>
            </section>
        </div>
    </AdminLayout>
</template>
