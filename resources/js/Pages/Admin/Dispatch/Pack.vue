<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, ChevronLeft, Download, Undo2, Warehouse } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import Card from '@/Components/ui/Card.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    request: { type: Object, required: true },
    packList: { type: Array, required: true },
});

// Ticked off as the store keeper walks the store. Local to this screen -
// nothing to save, it is a memory aid.
const packed = ref({});

const sending = computed(() => props.request.lines.filter((line) => (line.approved ?? 0) > 0));

const form = useForm({
    lines: Object.fromEntries(sending.value.map((line) => [line.id, line.approved])),
    carrier_name: '',
    vehicle_number: '',
});

const search = ref('');

/*
 * Hides rows only. Every line keeps its number and its tick whether or not it
 * is on screen, so hunting for one item mid-pack cannot lose the rest.
 */
const groups = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.packList;

    return props.packList
        .map((group) => ({
            ...group,
            lines: group.lines.filter((line) => line.item.toLowerCase().includes(term)),
        }))
        .filter((group) => group.lines.length);
});

const visibleSending = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return sending.value;

    return sending.value.filter((line) => line.item.toLowerCase().includes(term));
});

const totalLines = computed(() => props.packList.reduce((sum, group) => sum + group.lines.length, 0));
const packedCount = computed(() => Object.values(packed.value).filter(Boolean).length);
const allPacked = computed(() => totalLines.value > 0 && packedCount.value === totalLines.value);

const progress = computed(() =>
    totalLines.value ? Math.round((packedCount.value / totalLines.value) * 100) : 0,
);

/*
 * Anything the packer has knocked down from what was approved. Shown as a
 * short list rather than left for them to spot among twenty steppers, because
 * this is the one thing on the screen the branch will notice tomorrow.
 */
const short = computed(() =>
    sending.value
        .filter((line) => (form.lines[line.id] ?? 0) < line.approved)
        .map((line) => ({
            id: line.id,
            item: line.item,
            approved: line.approved_text,
            sending: `${form.lines[line.id]} ${line.unit}`,
        })),
);

function toggle(id) {
    packed.value[id] = !packed.value[id];
}

function tickEverything() {
    props.packList.forEach((group) => {
        group.lines.forEach((line) => {
            packed.value[line.id] = true;
        });
    });
}

function startAgain() {
    packed.value = {};
}

function restore(id) {
    const line = sending.value.find((candidate) => candidate.id === id);
    form.lines[id] = line.approved;
}

function send() {
    form.post(`/admin/dispatch/${props.request.id}`);
}
</script>

<template>
    <AdminLayout
        :title="`Pack for ${request.branch}`"
        :subtitle="`${request.number} · asked ${request.sent_at_text}`"
    >
        <Head :title="`Pack for ${request.branch}`" />

        <template #header-action>
            <!-- A file to keep or hand to the driver, not the app printed. -->
            <a
                :href="`/admin/dispatch/${request.id}/pdf`"
                class="inline-flex min-h-touch items-center gap-2 rounded-control border border-line bg-surface px-4 text-body font-medium text-ink transition hover:border-primary hover:text-primary"
            >
                <Download :size="16" />
                Download PDF
            </a>
        </template>

        <Link
            href="/admin/dispatch"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="18" />
            Dispatch
        </Link>

        <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
            <!-- Grouped by where things are kept, so the store is walked once -->
            <div>
                <Card :padded="false" :title="`${packedCount} of ${totalLines} picked`">
                    <template #action>
                        <button
                            v-if="packedCount"
                            type="button"
                            class="flex min-h-touch items-center gap-1.5 px-2 text-body text-ink-soft transition hover:text-ink"
                            @click="startAgain"
                        >
                            <Undo2 :size="14" />
                            Start again
                        </button>
                        <button
                            v-else
                            type="button"
                            class="flex min-h-touch items-center gap-1.5 px-2 text-body font-medium text-primary"
                            @click="tickEverything"
                        >
                            <Check :size="14" />
                            Tick everything
                        </button>
                    </template>

                    <!-- How far down the store you are, without reading. -->
                    <div class="h-1 w-full bg-line">
                        <div
                            class="h-1 transition-all duration-300"
                            :class="allPacked ? 'bg-approved' : 'bg-primary'"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>

                    <div v-if="totalLines > 8" class="border-b border-line px-4 py-3 sm:px-5">
                        <SearchField v-model="search" hide-label placeholder="Find an item in this order" />
                    </div>

                    <div v-for="group in groups" :key="group.location">
                        <h3
                            class="flex items-center gap-2 border-b border-line bg-page px-4 py-2 text-helper font-medium uppercase tracking-wide text-ink-soft sm:px-5"
                        >
                            <Warehouse :size="14" aria-hidden="true" />
                            {{ group.location }}
                        </h3>

                        <ul class="divide-y divide-line">
                            <li v-for="line in group.lines" :key="line.id">
                                <button
                                    type="button"
                                    class="flex w-full min-h-touch items-center gap-3 px-4 py-3 text-left transition hover:bg-page sm:gap-4 sm:px-5"
                                    :aria-pressed="!!packed[line.id]"
                                    @click="toggle(line.id)"
                                >
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-control border-2 transition"
                                        :class="
                                            packed[line.id]
                                                ? 'border-approved bg-approved text-white'
                                                : 'border-line text-transparent'
                                        "
                                        aria-hidden="true"
                                    >
                                        <Check :size="14" stroke-width="3" />
                                    </span>

                                    <span
                                        class="min-w-0 flex-1 text-body"
                                        :class="packed[line.id] ? 'text-ink-muted line-through' : 'text-ink'"
                                    >
                                        {{ line.item }}
                                    </span>

                                    <span
                                        class="shrink-0 text-qty tabular"
                                        :class="packed[line.id] ? 'text-ink-muted' : 'text-ink'"
                                    >
                                        {{ line.approved_text }}
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </Card>
            </div>

            <!-- What actually left, and with whom -->
            <div class="space-y-4">
                <Card
                    title="What is going"
                    hint="Change a number only if you are sending less than was approved."
                    :padded="false"
                >
                    <div class="divide-y divide-line">
                        <div
                            v-for="line in visibleSending"
                            :key="line.id"
                            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 px-4 py-2.5 sm:px-5"
                        >
                            <span class="min-w-0 flex-1 text-body text-ink">{{ line.item }}</span>
                            <QtyStepper
                                v-model="form.lines[line.id]"
                                :step="line.step"
                                :decimals="line.decimals"
                                :max="line.approved"
                                :unit="line.unit"
                                :label="line.item"
                            />
                        </div>
                    </div>
                </Card>

                <!-- Said plainly, before it is sent, not discovered tomorrow. -->
                <Card
                    v-if="short.length"
                    :title="`${short.length} going short`"
                    hint="The branch is told what it is getting, not what it asked for."
                    :padded="false"
                >
                    <div class="divide-y divide-line">
                        <div
                            v-for="line in short"
                            :key="line.id"
                            class="flex flex-wrap items-baseline justify-between gap-x-3 px-4 py-2.5 sm:px-5"
                        >
                            <span class="min-w-0 flex-1 text-body text-ink">{{ line.item }}</span>
                            <span class="text-helper text-ink-soft">
                                <span class="tabular line-through">{{ line.approved }}</span>
                                →
                                <span class="tabular font-medium text-partial">{{ line.sending }}</span>
                            </span>
                            <button
                                type="button"
                                class="min-h-touch px-2 text-helper text-primary"
                                @click="restore(line.id)"
                            >
                                Undo
                            </button>
                        </div>
                    </div>
                </Card>

                <Card title="Who is taking it">
                    <div class="space-y-4">
                        <TextField
                            v-model="form.carrier_name"
                            label="Person or company"
                            :error="form.errors.carrier_name"
                        />
                        <TextField
                            v-model="form.vehicle_number"
                            label="Vehicle number"
                            :error="form.errors.vehicle_number"
                        />

                        <AppButton
                            block
                            size="lg"
                            :loading="form.processing"
                            loading-text="Saving…"
                            @click="send"
                        >
                            Mark as sent
                        </AppButton>

                        <p class="text-helper text-ink-soft">
                            This takes the stock out of the main store and tells
                            {{ request.branch }} it is coming.
                        </p>
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
