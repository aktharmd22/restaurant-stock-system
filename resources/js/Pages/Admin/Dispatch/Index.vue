<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';

defineProps({
    requests: { type: Array, required: true },
});
</script>

<template>
    <AdminLayout title="Dispatch">
        <Head title="Dispatch" />

        <div v-if="requests.length" class="space-y-2">
            <SpineCard
                v-for="request in requests"
                :key="request.id"
                :status="request.is_late ? 'late' : request.status"
            >
                <Link :href="`/admin/dispatch/${request.id}`" class="block p-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-body font-medium text-ink">{{ request.branch }}</p>
                            <p class="mt-0.5 text-helper text-ink-soft">
                                {{ request.item_count }} items · {{ request.number }} ·
                                approved {{ request.sent_at_text }}
                            </p>
                            <p class="mt-2 inline-flex items-center gap-1 text-body font-medium text-primary">
                                Open pack list
                                <ChevronRight :size="20" />
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <StatusPill v-if="request.is_late" status="late" />
                            <StatusPill :status="request.status" />
                        </div>
                    </div>
                </Link>
            </SpineCard>
        </div>

        <EmptyState
            v-else
            icon="Truck"
            title="Nothing to pack"
            message="Approved requests show up here, grouped by where things are kept."
        />
    </AdminLayout>
</template>
