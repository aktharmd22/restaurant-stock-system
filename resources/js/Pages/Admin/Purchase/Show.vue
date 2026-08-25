<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Phone } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import StatusText from '@/Components/ui/StatusText.vue';

const props = defineProps({
    order: { type: Object, required: true },
    currency: { type: String, default: '₹' },
});

const outstanding = computed(() => props.order.lines.filter((line) => line.outstanding > 0));

// Everything still due is the normal case for a delivery, so start there.
const form = useForm({
    lines: Object.fromEntries(outstanding.value.map((line) => [line.id, line.outstanding])),
});

const anything = computed(() => Object.values(form.lines).some((qty) => Number(qty) > 0));

function receive() {
    form.post(`/admin/purchase/${props.order.id}/receive`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :title="order.number">
        <Head :title="order.number" />

        <Link
            href="/admin/purchase"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Purchase
        </Link>

        <div class="grid max-w-5xl gap-4 lg:grid-cols-[1fr_320px]">
            <div class="space-y-4">
                <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-title text-ink">{{ order.supplier }}</h2>
                            <p class="mt-1 text-helper text-ink-soft">
                                {{ order.number }} · to {{ order.branch }} · placed {{ order.placed }}
                                <span v-if="order.expected"> · due {{ order.expected }}</span>
                            </p>
                            <p v-if="order.supplier_phone" class="mt-2">
                                <a
                                    :href="`tel:${order.supplier_phone}`"
                                    class="inline-flex min-h-touch items-center gap-2 text-body text-primary"
                                >
                                    <Phone :size="20" />
                                    {{ order.supplier_phone }}
                                </a>
                            </p>
                        </div>

                        <StatusText :status="order.tone" :label="order.status_label" />
                    </div>

                    <p v-if="order.note" class="mt-3 text-body text-ink-soft">"{{ order.note }}"</p>
                </section>

                <section class="overflow-hidden rounded-card border border-line bg-surface">
                    <h2 class="border-b border-line px-card py-3 text-heading text-ink lg:px-card-lg">
                        What was ordered
                    </h2>

                    <div class="divide-y divide-line">
                        <div
                            v-for="line in order.lines"
                            :key="line.id"
                            class="flex flex-wrap items-center justify-between gap-3 p-card lg:p-card-lg"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-body text-ink">{{ line.item }}</p>
                                <p class="text-helper text-ink-soft">
                                    Ordered <span class="tabular">{{ line.ordered_text }}</span>
                                    · arrived <span class="tabular">{{ line.received_text }}</span>
                                    <span v-if="line.outstanding > 0" class="text-partial">
                                        · <span class="tabular">{{ line.outstanding_text }}</span> still due
                                    </span>
                                </p>
                            </div>

                            <p class="shrink-0 text-body tabular text-ink-soft">
                                {{ currency }}{{ line.unit_price.toLocaleString('en-IN') }} / {{ line.unit }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-line px-card py-3 lg:px-card-lg">
                        <p class="text-body text-ink-soft">Order total</p>
                        <p class="text-qty tabular text-ink">
                            {{ currency }}{{ order.total.toLocaleString('en-IN') }}
                        </p>
                    </div>
                </section>
            </div>

            <div class="lg:sticky lg:top-24 lg:self-start">
                <section
                    v-if="outstanding.length"
                    class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg"
                >
                    <h2 class="text-heading text-ink">What turned up</h2>
                    <p class="text-helper text-ink-soft">
                        Change a number only if less arrived than was ordered.
                    </p>

                    <div v-for="line in outstanding" :key="line.id" class="space-y-1">
                        <p class="text-body text-ink">{{ line.item }}</p>
                        <QtyStepper
                            v-model="form.lines[line.id]"
                            :step="line.step"
                            :decimals="line.decimals"
                            :max="line.outstanding"
                            :unit="line.unit"
                            :label="line.item"
                        />
                    </div>

                    <AppButton
                        block
                        size="lg"
                        :disabled="!anything"
                        :loading="form.processing"
                        loading-text="Saving…"
                        @click="receive"
                    >
                        Add to stock
                    </AppButton>
                </section>

                <section v-else class="rounded-card border border-approved/20 bg-approved-bg p-card lg:p-card-lg">
                    <p class="text-body text-approved">Everything on this order has arrived.</p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
