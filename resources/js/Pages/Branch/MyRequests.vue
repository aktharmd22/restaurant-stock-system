<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ListRow from '@/Components/ui/ListRow.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import StatusText from '@/Components/ui/StatusText.vue';

const props = defineProps({
    requests: { type: Object, required: true },
});

/*
 * Grouped by day. A flat run of thirty rows has no rhythm; broken by "Today"
 * and "Yesterday" it tells you where you are without reading a date.
 */
const groups = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    const buckets = new Map();

    props.requests.data.forEach((request) => {
        const sent = request.sent_at ? new Date(request.sent_at) : null;
        let key = 'Earlier';

        if (sent) {
            const day = new Date(sent);
            day.setHours(0, 0, 0, 0);

            if (day.getTime() === today.getTime()) key = 'Today';
            else if (day.getTime() === yesterday.getTime()) key = 'Yesterday';
            else key = sent.toLocaleDateString('en-GB', { day: 'numeric', month: 'long' });
        }

        if (!buckets.has(key)) buckets.set(key, []);
        buckets.get(key).push(request);
    });

    return [...buckets.entries()];
});

const timeOf = (request) => (request.sent_at_text ?? '').split(', ').pop();
</script>

<template>
    <BranchLayout title="My requests" subtitle="Everything you have asked for, newest first">
        <Head title="My requests" />

        <template #action>
            <AppButton href="/b/ask" size="lg" class="w-full lg:w-auto">Ask for stock</AppButton>
        </template>

        <template v-if="requests.data.length">
            <Card :padded="false">
                <div v-for="[label, rows] in groups" :key="label">
                    <!-- Day marker. Quiet, but it breaks the run of rows. -->
                    <p class="border-b border-line bg-page/60 px-4 py-1.5 text-helper text-ink-soft sm:px-5">
                        {{ label }}
                    </p>

                    <div class="divide-y divide-line">
                        <ListRow
                            v-for="request in rows"
                            :key="request.id"
                            :href="`/b/requests/${request.id}`"
                            :status="request.status"
                        >
                            <span class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-body font-medium text-ink">
                                    {{ request.item_count }} item<span v-if="request.item_count !== 1">s</span>
                                </span>
                                <span class="text-helper text-ink-soft">{{ timeOf(request) }}</span>
                                <span v-if="request.is_late" class="text-helper text-partial">
                                    after cut-off
                                </span>
                            </span>

                            <span class="mt-0.5 block truncate text-helper text-ink-muted">
                                {{ request.number }}
                            </span>

                            <template #end>
                                <!-- Fixed width, so the words make a column
                                     down the page rather than a ragged edge. -->
                                <span class="flex w-[124px] justify-end">
                                    <StatusText :status="request.status" size="sm" />
                                </span>
                            </template>
                        </ListRow>
                    </div>
                </div>
            </Card>

            <Pagination :links="requests.links" :meta="requests" />
        </template>

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
