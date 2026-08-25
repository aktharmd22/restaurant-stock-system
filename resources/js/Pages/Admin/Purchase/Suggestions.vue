<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';

defineProps({
    rows: { type: Array, required: true },
    suppliers: { type: Array, default: () => [] },
});
</script>

<template>
    <AdminLayout title="What to buy">
        <Head title="What to buy" />

        <template #header-action>
            <AppButton href="/admin/purchase/new">New order</AppButton>
        </template>

        <Link
            href="/admin/purchase"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Purchase
        </Link>

        <p class="mb-4 max-w-2xl text-body text-ink-soft">
            Every branch's shortfall added to the main store's own shelf, minus what is already free
            here. This is what you would need to cover everyone today.
        </p>

        <div v-if="rows.length" class="space-y-2">
            <SpineCard v-for="row in rows" :key="row.id" status="low">
                <div class="flex flex-wrap items-center gap-3 p-card">
                    <div class="min-w-0 flex-1">
                        <p class="text-body font-medium text-ink">{{ row.name }}</p>
                        <p class="text-helper text-ink-soft">
                            {{ row.category }} · <span class="tabular">{{ row.free_at_main_text }}</span> free here
                            · branches need <span class="tabular">{{ row.branches_need_text }}</span>
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-helper text-ink-soft">Buy about</p>
                        <p class="text-qty tabular text-partial">{{ row.suggested_text }}</p>
                    </div>
                </div>
            </SpineCard>
        </div>

        <EmptyState
            v-else
            icon="ClipboardCheck"
            title="Nothing needs buying"
            message="The main store can cover every branch's shelf right now."
        />
    </AdminLayout>
</template>
