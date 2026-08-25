<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Camera, CheckCircle2 } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    request: { type: Object, required: true },
    reasons: { type: Array, required: true },
});

/*
 * Everything arrived is the normal case, so that is the starting state and the
 * whole job is one tap. Only a short line asks any questions.
 */
const lines = ref(
    Object.fromEntries(
        props.request.lines
            .filter((line) => (line.sent ?? 0) > 0)
            .map((line) => [line.id, { qty: line.sent, reason: null, note: '', photo: null }]),
    ),
);

const form = useForm({ lines: {}, photos: {} });

const sentLines = computed(() => props.request.lines.filter((line) => (line.sent ?? 0) > 0));

const search = ref('');

// Hides rows only: what has already been typed stays in `lines`.
const visibleLines = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return sentLines.value;

    return sentLines.value.filter((line) => line.item.toLowerCase().includes(term));
});

const shortLines = computed(() =>
    sentLines.value.filter((line) => lines.value[line.id].qty < line.sent),
);

const allArrived = computed(() => shortLines.value.length === 0);

const missingReason = computed(() =>
    shortLines.value.some((line) => !lines.value[line.id].reason),
);

function pickPhoto(lineId, event) {
    lines.value[lineId].photo = event.target.files?.[0] ?? null;
}

function confirm() {
    form.lines = Object.fromEntries(
        Object.entries(lines.value).map(([id, line]) => [
            id,
            { qty: line.qty, reason: line.reason, note: line.note },
        ]),
    );

    form.photos = Object.fromEntries(
        Object.entries(lines.value)
            .filter(([, line]) => line.photo)
            .map(([id, line]) => [id, line.photo]),
    );

    form.post(`/b/receive/${props.request.id}`, { forceFormData: true });
}
</script>

<template>
    <BranchLayout title="Confirm what arrived" back="/b/receive">
        <Head title="Confirm what arrived" />

        <div
            v-if="allArrived"
            class="mb-4 flex items-center gap-3 rounded-card border border-approved/20 bg-approved-bg p-card"
        >
            <CheckCircle2 :size="24" class="shrink-0 text-approved" aria-hidden="true" />
            <p class="text-body text-ink">
                Everything is set to "all arrived". Change only what is short.
            </p>
        </div>

        <Card :padded="false">
            <div v-if="sentLines.length > 8" class="border-b border-line px-4 py-3 sm:px-5">
                <SearchField v-model="search" hide-label placeholder="Find an item in this delivery" />
            </div>

            <div class="divide-y divide-line">
                <div
                    v-for="line in visibleLines"
                    :key="line.id"
                    class="px-4 py-3.5 sm:px-5"
                    :class="lines[line.id].qty < line.sent ? 'bg-partial-bg/40' : ''"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-body font-medium text-ink">{{ line.item }}</p>
                            <p class="text-helper text-ink-soft">Sent {{ line.sent_text }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <p class="text-body text-ink-soft">Arrived</p>
                        <QtyStepper
                            v-model="lines[line.id].qty"
                            :step="line.step"
                            :decimals="line.decimals"
                            :max="line.sent"
                            :unit="line.unit"
                            :label="line.item"
                        />
                    </div>

                    <!-- Only asked when something is actually missing -->
                    <div v-if="lines[line.id].qty < line.sent" class="mt-4 border-t border-line pt-4">
                        <p class="text-body text-ink">What happened to the rest?</p>

                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="reason in reasons"
                                :key="reason.value"
                                type="button"
                                class="min-h-touch rounded-full border px-4 text-body transition"
                                :class="
                                    lines[line.id].reason === reason.value
                                        ? 'border-partial bg-partial-bg font-medium text-partial'
                                        : 'border-line bg-surface text-ink-soft'
                                "
                                @click="lines[line.id].reason = reason.value"
                            >
                                {{ reason.label }}
                            </button>
                        </div>

                        <label
                            class="mt-3 inline-flex min-h-touch cursor-pointer items-center gap-2 rounded-control border border-line px-4 text-body text-ink"
                        >
                            <Camera :size="20" />
                            {{ lines[line.id].photo ? 'Photo added' : 'Add a photo' }}
                            <input
                                type="file"
                                accept="image/*"
                                capture="environment"
                                class="sr-only"
                                @change="(event) => pickPhoto(line.id, event)"
                            />
                        </label>
                    </div>
                </div>
            </div>
        </Card>

        <p v-if="missingReason" class="mt-4 rounded-control bg-partial-bg p-3 text-body text-partial">
            Pick what happened to the missing items before you confirm.
        </p>

        <template #action>
            <AppButton
                size="lg"
                block
                :disabled="missingReason"
                :loading="form.processing"
                loading-text="Saving…"
                @click="confirm"
            >
                Confirm what arrived
            </AppButton>
        </template>
    </BranchLayout>
</template>
