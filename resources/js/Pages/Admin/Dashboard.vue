<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
</script>

<template>
    <AdminLayout title="Dashboard">
        <Head title="Dashboard" />

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <StatCard label="Waiting for you" :value="0" icon="Clock" tone="waiting" href="/admin/requests" />
            <StatCard label="To send today" :value="0" icon="Truck" tone="primary" href="/admin/dispatch" />
            <StatCard label="In transit" :value="0" icon="MapPin" tone="neutral" href="/admin/dispatch" />
            <StatCard label="Low stock items" :value="0" icon="TrendingDown" tone="partial" href="/admin/stock" />
        </div>

        <div class="mt-4 lg:mt-6">
            <EmptyState
                icon="Inbox"
                title="Nothing needs you right now"
                message="New requests from branches will appear here."
            />
        </div>
    </AdminLayout>
</template>
