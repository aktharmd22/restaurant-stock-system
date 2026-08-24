<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, Truck, Undo2 } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';

const props = defineProps({
    request: { type: Object, required: true },
    canCancel: { type: Boolean, default: false },
    justSent: { type: Boolean, default: false },
});

// Ten seconds to change your mind straight after sending. After that the
// request is normal work and cancelling is a deliberate act.
const undoSecondsLeft = ref(props.justSent ? 10 : 0);
const cancelOpen = ref(false);
let undoTimer = null;

onMounted(() => {
    if (!props.justSent) return;

    undoTimer = setInterval(() => {
        undoSecondsLeft.value -= 1;
        if (undoSecondsLeft.value <= 0) clearInterval(undoTimer);
    }, 1000);
});

onBeforeUnmount(() => clearInterval(undoTimer));

const showUndo = computed(() => props.justSent && undoSecondsLeft.value > 0 && props.canCancel);

function cancel(reason = null) {
    router.post(`/b/requests/${props.request.id}/cancel`, { reason });
}
</script>

<template>
    <BranchLayout :title="request.sent_at_text" back="/b/requests">
        <Head title="Request" />

        <!-- Straight after sending: a plain confirmation, and a way back out -->
        <div
            v-if="justSent"
            class="mb-4 flex items-start gap-3 rounded-card border border-approved/20 bg-approved-bg p-card"
        >
            <CheckCircle2 :size="24" class="mt-0.5 shrink-0 text-approved" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="text-heading text-approved">Request sent</p>
                <p class="mt-0.5 text-body text-ink">
                    The main store can see it now. You will be told when it is approved.
                </p>

                <button
                    v-if="showUndo"
                    type="button"
                    class="mt-3 inline-flex min-h-touch items-center gap-2 rounded-control border border-approved/30 bg-surface px-4 text-body font-medium text-ink"
                    @click="cancel('Changed my mind')"
                >
                    <Undo2 :size="20" />
                    Undo ({{ undoSecondsLeft }})
                </button>
            </div>
        </div>

        <SpineCard :status="request.status">
            <div class="p-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-body font-medium text-ink">{{ request.number }}</p>
                        <p class="text-helper text-ink-soft">Sent {{ request.sent_at_text }}</p>
                    </div>
                    <StatusPill :status="request.status" size="lg" />
                </div>

                <p v-if="request.note" class="mt-3 text-body text-ink-soft">"{{ request.note }}"</p>

                <div
                    v-if="request.status === 'sent'"
                    class="mt-3 flex items-center gap-2 rounded-control bg-primary-light p-3 text-body text-primary"
                >
                    <Truck :size="20" />
                    <span>
                        On the way<span v-if="request.carrier"> with {{ request.carrier }}</span>
                        <span v-if="request.vehicle"> ({{ request.vehicle }})</span>
                    </span>
                </div>

                <p v-if="request.cancel_reason" class="mt-3 text-body text-ink-soft">
                    Cancelled: {{ request.cancel_reason }}
                </p>
            </div>
        </SpineCard>

        <!-- Three plain columns: what you asked, what was approved, what turned up -->
        <div class="mt-4 space-y-2">
            <SpineCard v-for="line in request.lines" :key="line.id" :status="line.tone">
                <div class="p-card">
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 text-body font-medium text-ink">{{ line.item }}</p>
                        <StatusPill :status="line.tone" :label="line.status_label" />
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-control bg-page py-2">
                            <dt class="text-helper text-ink-soft">You asked</dt>
                            <dd class="text-qty tabular text-ink">{{ line.requested_text }}</dd>
                        </div>
                        <div class="rounded-control bg-page py-2">
                            <dt class="text-helper text-ink-soft">Approved</dt>
                            <dd class="text-qty tabular text-ink">
                                {{ line.approved_text ?? '—' }}
                            </dd>
                        </div>
                        <div class="rounded-control bg-page py-2">
                            <dt class="text-helper text-ink-soft">Arrived</dt>
                            <dd class="text-qty tabular text-ink">
                                {{ line.received_text ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <p v-if="line.reason" class="mt-3 rounded-control bg-partial-bg p-3 text-body text-partial">
                        {{ line.reason }}
                    </p>
                </div>
            </SpineCard>
        </div>

        <template v-if="canCancel && !justSent" #action>
            <AppButton variant="danger" size="lg" block @click="cancelOpen = true">
                Cancel this request
            </AppButton>
        </template>

        <BottomSheet
            :open="cancelOpen"
            title="Cancel this request?"
            description="The main store will see that you called it back. Anything already set aside goes back on the shelf."
            @close="cancelOpen = false"
        >
            <div class="space-y-2">
                <button
                    v-for="reason in ['Not needed now', 'Asked by mistake', 'Got it another way']"
                    :key="reason"
                    type="button"
                    class="flex min-h-touch w-full items-center rounded-control border border-line px-4 text-body text-ink hover:border-primary hover:bg-primary-light"
                    @click="cancel(reason)"
                >
                    {{ reason }}
                </button>
            </div>

            <template #footer>
                <AppButton variant="secondary" block size="lg" @click="cancelOpen = false">
                    Keep the request
                </AppButton>
            </template>
        </BottomSheet>
    </BranchLayout>
</template>
