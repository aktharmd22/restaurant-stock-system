<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';

defineProps({
    requests: { type: Object, required: true },
});
</script>

<template>
    <BranchLayout title="My requests">
        <Head title="My requests" />

        <div v-if="requests.data.length" class="space-y-2">
            <SpineCard v-for="item in requests.data" :key="item.id" :status="item.status">
                <Link :href="`/b/requests/${item.id}`" class="block p-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-body font-medium text-ink">{{ item.sent_at_text }}</p>
                            <p class="mt-0.5 text-helper text-ink-soft">
                                {{ item.item_count }} item<span v-if="item.item_count !== 1">s</span>
                                <span v-if="item.is_late"> · sent after cut-off</span>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <StatusPill :status="item.status" />
                            <ChevronRight :size="20" class="text-ink-muted" />
                        </div>
                    </div>
                </Link>
            </SpineCard>

            <Pagination :links="requests.links" :meta="requests" />
        </div>

        <EmptyState
            v-else
            icon="ClipboardList"
            title="No requests yet"
            message="Everything you ask for shows up here, with what was approved and what arrived."
        >
            <template #action>
                <AppButton href="/b/ask">Ask for stock</AppButton>
            </template>
        </EmptyState>
    </BranchLayout>
</template>
