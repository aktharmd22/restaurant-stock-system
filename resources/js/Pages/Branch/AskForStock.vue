<script setup>
import { Head } from '@inertiajs/vue3';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
</script>

<template>
    <BranchLayout title="Ask for stock" back="/b">
        <Head title="Ask for stock" />

        <EmptyState icon="Package" title="No items yet" message="Your admin adds items to the list." />
    </BranchLayout>
</template>
