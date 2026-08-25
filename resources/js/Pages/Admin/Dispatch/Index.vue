<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ListRow from '@/Components/ui/ListRow.vue';
import StatusText from '@/Components/ui/StatusText.vue';

const props = defineProps({
    requests: { type: Array, required: true },
});

// Late first. It is the only thing on this screen that changes what you do next.
const late = computed(() => props.requests.filter((request) => request.is_late));
const rest = computed(() => props.requests.filter((request) => !request.is_late));

const totalItems = computed(() =>
    props.requests.reduce((sum, request) => sum + request.item_count, 0),
);
</script>

<template>
    <AdminLayout
        title="Dispatch"
        :subtitle="
            requests.length
                ? `${requests.length} to pack · ${totalItems} items in total`
                : 'Nothing waiting to be packed'
        "
    >
        <Head title="Dispatch" />

        <div v-if="requests.length" class="space-y-4">
            <!-- Anything late is pulled out, not just tinted in place. -->
            <Card v-if="late.length" :padded="false" title="Late — pack these first">
                <div class="divide-y divide-line">
                    <ListRow
                        v-for="request in late"
                        :key="request.id"
                        :href="`/admin/dispatch/${request.id}`"
                        status="late"
                    >
                        <span class="block truncate text-body font-medium text-ink">
                            {{ request.branch }}
                        </span>
                        <span class="mt-0.5 block truncate text-helper text-ink-soft">
                            {{ request.number }} · approved {{ request.sent_at_text }}
                        </span>

                        <template #end>
                            <span class="hidden w-12 text-right sm:block">
                                <span class="block text-body tabular text-ink">{{ request.item_count }}</span>
                                <span class="block text-helper text-ink-muted">items</span>
                            </span>
                            <span class="flex w-[124px] justify-end"><StatusText status="late" size="sm" /></span>
                        </template>
                    </ListRow>
                </div>
            </Card>

            <Card :padded="false" :title="late.length ? 'Everything else' : 'Ready to pack'">
                <div class="divide-y divide-line">
                    <ListRow
                        v-for="request in rest"
                        :key="request.id"
                        :href="`/admin/dispatch/${request.id}`"
                        :status="request.status"
                    >
                        <span class="block truncate text-body font-medium text-ink">
                            {{ request.branch }}
                        </span>
                        <span class="mt-0.5 block truncate text-helper text-ink-soft">
                            {{ request.number }} · approved {{ request.sent_at_text }}
                        </span>

                        <template #end>
                            <span class="hidden w-12 text-right sm:block">
                                <span class="block text-body tabular text-ink">{{ request.item_count }}</span>
                                <span class="block text-helper text-ink-muted">items</span>
                            </span>
                            <span class="flex w-[124px] justify-end"><StatusText :status="request.status" size="sm" /></span>
                        </template>
                    </ListRow>
                </div>
            </Card>
        </div>

        <EmptyState
            v-else
            icon="Truck"
            title="Nothing to pack"
            message="Approved requests show up here, grouped by where things are kept."
        />
    </AdminLayout>
</template>
