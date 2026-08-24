<script setup>
import { Head } from '@inertiajs/vue3';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
</script>

<template>
    <BranchLayout>
        <Head title="Home" />

        <EmptyState
            icon="ClipboardList"
            title="No requests yet"
            message="Tap below to ask the main store for stock."
        />

        <template #action>
            <AppButton :href="route('branch.ask')" size="lg" block>Ask for stock</AppButton>
        </template>
    </BranchLayout>
</template>
