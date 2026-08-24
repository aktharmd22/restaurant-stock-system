<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Truck } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';

defineProps({
    deliveries: { type: Array, required: true },
});
</script>

<template>
    <BranchLayout title="Receive delivery">
        <Head title="Receive delivery" />

        <div v-if="deliveries.length" class="space-y-2">
            <SpineCard v-for="delivery in deliveries" :key="delivery.id" status="sent">
                <Link :href="`/b/receive/${delivery.id}`" class="block p-card">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary-light text-primary">
                            <Truck :size="20" aria-hidden="true" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-body font-medium text-ink">
                                {{ delivery.item_count }} item<span v-if="delivery.item_count !== 1">s</span>
                            </p>
                            <p class="mt-0.5 text-helper text-ink-soft">
                                <span v-if="delivery.carrier">With {{ delivery.carrier }}</span>
                                <span v-if="delivery.vehicle"> · {{ delivery.vehicle }}</span>
                                <span v-if="!delivery.carrier && !delivery.vehicle">On the way</span>
                            </p>
                            <p class="mt-2 inline-flex items-center gap-1 text-body font-medium text-primary">
                                Confirm what arrived
                                <ChevronRight :size="20" />
                            </p>
                        </div>
                    </div>
                </Link>
            </SpineCard>
        </div>

        <EmptyState
            v-else
            icon="PackageCheck"
            title="Nothing on the way"
            message="When the main store sends goods, they show up here to confirm."
        />
    </BranchLayout>
</template>
