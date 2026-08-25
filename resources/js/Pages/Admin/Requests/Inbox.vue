<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, TriangleAlert } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import Card from '@/Components/ui/Card.vue';
import ListRow from '@/Components/ui/ListRow.vue';
import StatusText from '@/Components/ui/StatusText.vue';

const props = defineProps({
    requests: { type: Object, required: true },
    selected: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    reasons: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
});

const saving = ref(false);

/*
 * One decision per line: how much to approve, and why if it is not the full
 * amount. Everything starts at "yes, all of it", because that is the common
 * case and the admin should only have to touch the exceptions.
 */
const decisions = ref({});

function resetDecisions() {
    decisions.value = Object.fromEntries(
        (props.selected?.lines ?? []).map((line) => [
            line.id,
            { qty: line.approved ?? line.requested, reason_code: line.reason ? 'other' : null, note: '' },
        ]),
    );
}

resetDecisions();
watch(() => props.selected?.id, resetDecisions);

const isWaiting = computed(() => props.selected?.status === 'waiting');

const summary = computed(() => {
    const lines = props.selected?.lines ?? [];
    let approved = 0;
    let reduced = 0;
    let rejected = 0;

    lines.forEach((line) => {
        const qty = decisions.value[line.id]?.qty ?? 0;
        if (qty <= 0) rejected += 1;
        else if (qty < line.requested) reduced += 1;
        else approved += 1;
    });

    return { approved, reduced, rejected };
});

// A cut line without a reason is the one thing that must not be saveable.
const missingReasons = computed(() =>
    (props.selected?.lines ?? []).filter((line) => {
        const decision = decisions.value[line.id];
        if (!decision) return false;
        return decision.qty < line.requested && !decision.reason_code;
    }),
);

function needsReason(line) {
    return (decisions.value[line.id]?.qty ?? 0) < line.requested;
}

function reject(line) {
    decisions.value[line.id].qty = 0;
}

function restore(line) {
    decisions.value[line.id].qty = line.requested;
    decisions.value[line.id].reason_code = null;
}

const search = ref(props.filters.search ?? '');

// A separate box for the request that is open: forty-eight lines is a lot to
// scroll when the branch has rung up about one of them.
const lineSearch = ref('');

const visibleLines = computed(() => {
    const term = lineSearch.value.trim().toLowerCase();
    const lines = props.selected?.lines ?? [];

    return term ? lines.filter((line) => line.item.toLowerCase().includes(term)) : lines;
});

let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => filterBy({ search: value || undefined }), 300);
});

function filterBy(changes) {
    router.get('/admin/requests', { ...props.filters, search: search.value || undefined, ...changes, selected: undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function save() {
    saving.value = true;
    router.post(`/admin/requests/${props.selected.id}/approve`, { decisions: decisions.value }, {
        preserveScroll: true,
        onFinish: () => (saving.value = false),
    });
}

function approveAll() {
    saving.value = true;
    router.post(`/admin/requests/${props.selected.id}/approve-all`, {}, {
        preserveScroll: true,
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <AdminLayout title="Requests">
        <Head title="Requests" />

        <div class="grid gap-4 lg:grid-cols-[380px_1fr]">
            <!-- Left: the queue. Hidden on a phone once something is open. -->
            <div :class="selected ? 'hidden lg:block' : ''">
                <div class="space-y-2">
                    <SearchField v-model="search" hide-label placeholder="Branch or request number" />

                    <div class="grid grid-cols-2 gap-2">
                        <SelectField
                            label="Show"
                            :model-value="filters.status"
                            :options="statuses"
                            @update:model-value="(value) => filterBy({ status: value })"
                        />
                        <SelectField
                            label="Branch"
                            :model-value="filters.branch ?? ''"
                            placeholder="Every branch"
                            :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                            @update:model-value="(value) => filterBy({ branch: value || undefined })"
                        />
                    </div>
                </div>

                <div v-if="requests.data.length" class="mt-3">
                    <Card :padded="false">
                        <div class="divide-y divide-line">
                            <ListRow
                                v-for="request in requests.data"
                                :key="request.id"
                                :href="`/admin/requests?status=${filters.status}&selected=${request.id}`"
                                :status="request.is_late ? 'late' : request.status"
                                :chevron="false"
                                :class="selected?.id === request.id ? 'bg-primary-light/50' : ''"
                            >
                                <span class="block truncate text-body font-medium text-ink">
                                    {{ request.branch }}
                                </span>
                                <span class="mt-0.5 block truncate text-helper text-ink-soft">
                                    {{ request.item_count }} items · {{ request.sent_at_text }}
                                </span>

                                <template #end>
                                    <StatusText
                                        :status="request.is_late ? 'late' : request.status"
                                        size="sm"
                                    />
                                </template>
                            </ListRow>
                        </div>
                    </Card>

                    <Pagination :links="requests.links" :meta="requests" />
                </div>

                <EmptyState
                    v-else
                    class="mt-3"
                    icon="Inbox"
                    title="Nothing here"
                    message="No requests match what you picked."
                />
            </div>

            <!-- Right: the request being decided -->
            <div v-if="selected">
                <Link
                    :href="`/admin/requests?status=${filters.status}`"
                    class="-ml-3 mb-3 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft lg:hidden"
                >
                    <ChevronLeft :size="20" />
                    All requests
                </Link>

                <div class="rounded-card border border-line bg-surface">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line p-card lg:p-card-lg">
                        <div class="min-w-0">
                            <h2 class="text-title text-ink">{{ selected.branch }}</h2>
                            <p class="mt-1 text-helper text-ink-soft">
                                {{ selected.number }} · sent {{ selected.sent_at_text }}
                                <span v-if="selected.needed_by"> · needed by {{ selected.needed_by }}</span>
                            </p>
                            <p v-if="selected.note" class="mt-2 text-body text-ink-soft">
                                "{{ selected.note }}"
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-4">
                            <StatusText v-if="selected.is_late" status="late" />
                            <StatusText :status="selected.status" />
                        </div>
                    </div>

                    <div v-if="isWaiting && selected.can_approve" class="border-b border-line p-card lg:p-card-lg">
                        <AppButton block size="lg" :loading="saving" @click="approveAll">
                            Approve all as asked
                        </AppButton>
                        <p class="mt-2 text-helper text-ink-soft">
                            Most requests are fine as they are. Use the lines below only to change something.
                        </p>
                    </div>

                    <!-- Lines -->
                    <div
                        v-if="(selected.lines?.length ?? 0) > 8"
                        class="border-b border-line p-card lg:p-card-lg"
                    >
                        <SearchField
                            v-model="lineSearch"
                            hide-label
                            placeholder="Find an item in this request"
                        />
                    </div>

                    <div class="divide-y divide-line">
                        <div
                            v-for="line in visibleLines"
                            :key="line.id"
                            class="p-card lg:p-card-lg"
                            :class="line.is_short && isWaiting ? 'bg-partial-bg/50' : ''"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-body font-medium text-ink">{{ line.item }}</p>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                                        <p class="text-helper text-ink-soft">
                                            Asked <span class="tabular text-ink">{{ line.requested_text }}</span>
                                        </p>
                                        <p
                                            class="inline-flex items-center gap-1.5 text-helper"
                                            :class="line.is_short ? 'text-partial' : 'text-ink-soft'"
                                        >
                                            <TriangleAlert v-if="line.is_short" :size="16" />
                                            Free here
                                            <span class="tabular">{{ line.available_text }}</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Deciding -->
                                <div v-if="isWaiting && selected.can_approve" class="flex shrink-0 items-center gap-2">
                                    <QtyStepper
                                        v-model="decisions[line.id].qty"
                                        :step="line.step"
                                        :decimals="line.decimals"
                                        :max="line.requested"
                                        :unit="line.unit"
                                        :label="line.item"
                                    />

                                    <AppButton
                                        v-if="decisions[line.id].qty > 0"
                                        variant="ghost"
                                        @click="reject(line)"
                                    >
                                        Refuse
                                    </AppButton>
                                    <AppButton v-else variant="ghost" @click="restore(line)">
                                        Undo
                                    </AppButton>
                                </div>

                                <!-- Already decided -->
                                <div v-else class="shrink-0 text-right">
                                    <StatusText :status="line.tone" :label="line.status_label" size="sm" />
                                    <p class="mt-1 text-helper text-ink-soft">
                                        Approved <span class="tabular">{{ line.approved_text ?? '—' }}</span>
                                    </p>
                                </div>
                            </div>

                            <!-- A cut line always needs a reason the branch can read -->
                            <div v-if="isWaiting && selected.can_approve && needsReason(line)" class="mt-3">
                                <p class="text-helper text-ink-soft">Why?</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="reason in reasons"
                                        :key="reason.value"
                                        type="button"
                                        class="min-h-touch rounded-full border px-4 text-body transition"
                                        :class="
                                            decisions[line.id].reason_code === reason.value
                                                ? 'border-partial bg-partial-bg font-medium text-partial'
                                                : 'border-line bg-surface text-ink-soft'
                                        "
                                        @click="decisions[line.id].reason_code = reason.value"
                                    >
                                        {{ reason.label }}
                                    </button>
                                </div>

                                <input
                                    v-if="decisions[line.id].reason_code === 'other'"
                                    v-model="decisions[line.id].note"
                                    type="text"
                                    placeholder="Tell the branch what happened"
                                    class="mt-2 min-h-touch w-full rounded-control border border-line px-4 text-body text-ink focus:border-primary focus:ring-0"
                                />
                            </div>

                            <p v-else-if="line.reason" class="mt-2 text-helper text-partial">
                                {{ line.reason }}
                            </p>
                        </div>
                    </div>

                    <!-- What is about to be saved, before it is saved -->
                    <div
                        v-if="isWaiting && selected.can_approve"
                        class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface p-card lg:p-card-lg"
                    >
                        <p class="text-body text-ink">
                            <span class="tabular font-medium text-approved">{{ summary.approved }}</span> approved
                            ·
                            <span class="tabular font-medium text-partial">{{ summary.reduced }}</span> reduced
                            ·
                            <span class="tabular font-medium text-rejected">{{ summary.rejected }}</span> refused
                        </p>

                        <AppButton
                            size="lg"
                            :disabled="missingReasons.length > 0"
                            :loading="saving"
                            loading-text="Saving…"
                            @click="save"
                        >
                            Save decisions
                        </AppButton>
                    </div>

                    <p
                        v-if="missingReasons.length"
                        class="border-t border-line bg-partial-bg p-card text-body text-partial lg:p-card-lg"
                    >
                        Pick a reason for
                        {{ missingReasons.map((line) => line.item).join(', ') }}
                        before saving. The branch sees it.
                    </p>
                </div>
            </div>

            <EmptyState
                v-else
                class="hidden lg:flex"
                icon="Inbox"
                title="Pick a request"
                message="Choose one on the left to see what was asked for."
            />
        </div>
    </AdminLayout>
</template>
