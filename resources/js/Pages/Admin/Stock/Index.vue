<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
</script>

<template>
    <AdminLayout title="Stock">
        <Head title="Stock" />

        <EmptyState icon="Boxes" title="No stock yet" message="Add items and record a purchase to start." />
    </AdminLayout>
</template>
