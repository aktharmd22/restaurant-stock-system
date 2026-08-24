<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
</script>

<template>
    <AdminLayout title="Requests">
        <Head title="Requests" />

        <EmptyState icon="Inbox" title="No requests waiting" message="You are all caught up." />
    </AdminLayout>
</template>
