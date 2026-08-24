<script setup>
import { Head } from '@inertiajs/vue3';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
</script>

<template>
    <BranchLayout title="My requests">
        <Head title="My requests" />

        <EmptyState icon="ClipboardList" title="No requests yet" message="Everything you ask for shows up here.">
            <template #action>
                <AppButton :href="route('branch.ask')">Ask for stock</AppButton>
            </template>
        </EmptyState>
    </BranchLayout>
</template>
