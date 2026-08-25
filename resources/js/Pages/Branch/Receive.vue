<script setup>
import { Head } from '@inertiajs/vue3';
import { Truck } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ListRow from '@/Components/ui/ListRow.vue';

defineProps({
    deliveries: { type: Array, required: true },
});
</script>

<template>
    <BranchLayout title="Receive delivery">
        <Head title="Receive delivery" />

        <Card v-if="deliveries.length" :padded="false">
            <div class="divide-y divide-line">
                <ListRow
                    v-for="delivery in deliveries"
                    :key="delivery.id"
                    :href="`/b/receive/${delivery.id}`"
                >
                    <span class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary-light text-primary">
                            <Truck :size="18" aria-hidden="true" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-body font-medium text-ink">
                                {{ delivery.item_count }} item<span v-if="delivery.item_count !== 1">s</span>
                            </span>
                            <span class="block truncate text-helper text-ink-soft">
                                <span v-if="delivery.carrier">With {{ delivery.carrier }}</span>
                                <span v-if="delivery.vehicle"> · {{ delivery.vehicle }}</span>
                                <span v-if="!delivery.carrier && !delivery.vehicle">On the way</span>
                            </span>
                        </span>
                    </span>

                    <template #end>
                        <span class="hidden text-body font-medium text-primary sm:block">
                            Confirm what arrived
                        </span>
                    </template>
                </ListRow>
            </div>
        </Card>

        <EmptyState
            v-else
            icon="PackageCheck"
            title="Nothing on the way"
            message="When the main store sends goods, they show up here to confirm."
        />
    </BranchLayout>
</template>
