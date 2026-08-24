<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
</script>

<template>
    <AdminLayout title="Dispatch">
        <Head title="Dispatch" />

        <EmptyState icon="Truck" title="Nothing to pack" message="Approved requests show up here to send." />
    </AdminLayout>
</template>
