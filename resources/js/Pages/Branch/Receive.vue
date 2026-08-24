<script setup>
import { Head } from '@inertiajs/vue3';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
</script>

<template>
    <BranchLayout title="Receive delivery">
        <Head title="Receive delivery" />

        <EmptyState
            icon="PackageCheck"
            title="Nothing on the way"
            message="When the main store sends goods, they show up here to confirm."
        />
    </BranchLayout>
</template>
