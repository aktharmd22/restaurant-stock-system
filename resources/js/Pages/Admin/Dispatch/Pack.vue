<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Printer, Warehouse } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    request: { type: Object, required: true },
    packList: { type: Array, required: true },
});

// Ticked off as the store keeper walks the store. Local to this screen -
// nothing to save, it is a memory aid.
const packed = ref({});

const form = useForm({
    lines: Object.fromEntries(
        props.request.lines
            .filter((line) => (line.approved ?? 0) > 0)
            .map((line) => [line.id, line.approved]),
    ),
    carrier_name: '',
    vehicle_number: '',
});

const totalLines = computed(() => props.packList.reduce((sum, group) => sum + group.lines.length, 0));
const packedCount = computed(() => Object.values(packed.value).filter(Boolean).length);

function printPackList() {
    window.print();
}

function send() {
    form.post(`/admin/dispatch/${props.request.id}`);
}
</script>

<template>
    <AdminLayout :title="`Pack for ${request.branch}`">
        <Head :title="`Pack for ${request.branch}`" />

        <template #header-action>
            <AppButton variant="secondary" @click="printPackList">
                <template #icon><Printer :size="20" /></template>
                Print
            </AppButton>
        </template>

        <Link
            href="/admin/dispatch"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Dispatch
        </Link>

        <div class="grid gap-4 lg:grid-cols-[1fr_340px]">
            <!-- Grouped by where things are kept, so the store is walked once -->
            <div class="space-y-4">
                <p class="text-body text-ink-soft">
                    {{ packedCount }} of {{ totalLines }} picked · {{ request.number }}
                </p>

                <section
                    v-for="group in packList"
                    :key="group.location"
                    class="overflow-hidden rounded-card border border-line bg-surface"
                >
                    <h2 class="flex items-center gap-2 border-b border-line bg-page px-card py-3 text-heading text-ink lg:px-card-lg">
                        <Warehouse :size="20" class="text-ink-soft" aria-hidden="true" />
                        {{ group.location }}
                    </h2>

                    <ul class="divide-y divide-line">
                        <li v-for="line in group.lines" :key="line.id">
                            <label class="flex min-h-touch cursor-pointer items-center gap-4 p-card lg:p-card-lg">
                                <input
                                    v-model="packed[line.id]"
                                    type="checkbox"
                                    class="h-6 w-6 shrink-0 rounded border-line text-primary focus:ring-primary"
                                />
                                <span class="min-w-0 flex-1 text-body text-ink" :class="packed[line.id] ? 'line-through text-ink-muted' : ''">
                                    {{ line.item }}
                                </span>
                                <span class="shrink-0 text-qty tabular text-ink">{{ line.approved_text }}</span>
                            </label>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- What actually left, and with whom -->
            <div class="space-y-4">
                <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                    <h2 class="text-heading text-ink">What is going</h2>
                    <p class="text-helper text-ink-soft">
                        Change a number only if you are sending less than was approved.
                    </p>

                    <div
                        v-for="line in request.lines.filter((l) => (l.approved ?? 0) > 0)"
                        :key="line.id"
                        class="flex flex-wrap items-center justify-between gap-2"
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
                </section>

                <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                    <h2 class="text-heading text-ink">Who is taking it</h2>

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
                        This takes the stock out of the main store and tells the branch it is coming.
                    </p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
