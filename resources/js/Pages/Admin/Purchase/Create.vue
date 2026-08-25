<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Search, X } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    suppliers: { type: Array, required: true },
    branches: { type: Array, required: true },
    items: { type: Array, required: true },
    currency: { type: String, default: '₹' },
});

const search = ref('');
const chosen = ref({});

const form = useForm({
    supplier_id: '',
    branch_id: props.branches[0]?.id ?? '',
    expected_date: '',
    note: '',
    lines: [],
});

const matches = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return [];

    return props.items
        .filter((item) => item.name.toLowerCase().includes(term) && !chosen.value[item.id])
        .slice(0, 8);
});

const lines = computed(() => Object.values(chosen.value));

const total = computed(() =>
    lines.value.reduce((sum, line) => sum + (Number(line.qty) || 0) * (Number(line.unit_price) || 0), 0),
);

function add(item) {
    chosen.value[item.id] = { ...item, qty: 1, unit_price: '' };
    search.value = '';
}

function remove(id) {
    delete chosen.value[id];
}

function submit() {
    form.lines = lines.value.map((line) => ({
        item_id: line.id,
        qty: line.qty,
        unit_price: line.unit_price || 0,
    }));

    form.post('/admin/purchase');
}
</script>

<template>
    <AdminLayout title="New order">
        <Head title="New order" />

        <Link
            href="/admin/purchase"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Purchase
        </Link>

        <div class="grid max-w-5xl gap-4 lg:grid-cols-[1fr_320px]">
            <div class="space-y-4">
                <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                    <h2 class="text-heading text-ink">Who and where</h2>

                    <SelectField
                        v-model="form.supplier_id"
                        label="Supplier"
                        placeholder="Pick a supplier"
                        :options="suppliers.map((s) => ({ value: s.id, label: s.name }))"
                        :error="form.errors.supplier_id"
                    />

                    <SelectField
                        v-model="form.branch_id"
                        label="Deliver to"
                        :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                        :error="form.errors.branch_id"
                    />

                    <TextField
                        v-model="form.expected_date"
                        label="Expected date"
                        type="date"
                        :error="form.errors.expected_date"
                    />

                    <TextField v-model="form.note" label="Note (optional)" :error="form.errors.note" />
                </section>

                <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                    <h2 class="text-heading text-ink">What you are buying</h2>

                    <div class="relative mt-3">
                        <Search :size="20" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-muted" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search for an item to add"
                            aria-label="Search for an item to add"
                            class="min-h-control w-full rounded-control border border-line bg-surface pl-12 pr-4 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                        />

                        <div
                            v-if="matches.length"
                            class="absolute inset-x-0 top-full z-10 mt-1 overflow-hidden rounded-control border border-line bg-surface shadow-float"
                        >
                            <button
                                v-for="item in matches"
                                :key="item.id"
                                type="button"
                                class="flex min-h-touch w-full items-center justify-between gap-3 px-4 text-left text-body text-ink hover:bg-page"
                                @click="add(item)"
                            >
                                <span>{{ item.name }}</span>
                                <span class="text-helper text-ink-soft">{{ item.category }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="lines.length" class="mt-4 divide-y divide-line">
                        <div
                            v-for="line in lines"
                            :key="line.id"
                            class="flex flex-wrap items-center gap-3 py-3"
                        >
                            <p class="min-w-[140px] flex-1 text-body text-ink">{{ line.name }}</p>

                            <label class="flex items-center gap-2">
                                <span class="text-helper text-ink-soft">Qty</span>
                                <input
                                    v-model="line.qty"
                                    type="text"
                                    inputmode="decimal"
                                    class="min-h-touch w-24 rounded-control border border-line px-3 text-body tabular text-ink focus:border-primary focus:ring-0"
                                />
                                <span class="text-helper text-ink-soft">{{ line.unit }}</span>
                            </label>

                            <label class="flex items-center gap-2">
                                <span class="text-helper text-ink-soft">{{ currency }} per {{ line.unit }}</span>
                                <input
                                    v-model="line.unit_price"
                                    type="text"
                                    inputmode="decimal"
                                    class="min-h-touch w-24 rounded-control border border-line px-3 text-body tabular text-ink focus:border-primary focus:ring-0"
                                />
                            </label>

                            <button
                                type="button"
                                class="flex h-touch w-touch items-center justify-center rounded-control text-ink-muted hover:text-rejected"
                                :aria-label="`Remove ${line.name}`"
                                @click="remove(line.id)"
                            >
                                <X :size="20" />
                            </button>
                        </div>
                    </div>

                    <p v-else class="mt-4 text-body text-ink-soft">
                        Search above to add what you are ordering.
                    </p>

                    <p v-if="form.errors.lines" class="mt-2 text-helper text-rejected">{{ form.errors.lines }}</p>
                </section>
            </div>

            <div class="lg:sticky lg:top-24 lg:self-start">
                <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                    <h2 class="text-heading text-ink">Order total</h2>

                    <p class="text-stat tabular text-ink">
                        {{ currency }}{{ total.toLocaleString('en-IN', { maximumFractionDigits: 2 }) }}
                    </p>
                    <p class="text-helper text-ink-soft">
                        {{ lines.length }} item<span v-if="lines.length !== 1">s</span>
                    </p>

                    <AppButton
                        block
                        size="lg"
                        :disabled="!lines.length || !form.supplier_id"
                        :loading="form.processing"
                        loading-text="Saving…"
                        @click="submit"
                    >
                        Place the order
                    </AppButton>

                    <p class="text-helper text-ink-soft">
                        Placing an order moves no stock. Stock arrives when you record what turned up.
                    </p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
