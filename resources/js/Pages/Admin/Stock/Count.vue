<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Warehouse } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    count: { type: Object, required: true },
});

const form = useForm({
    lines: Object.fromEntries(
        props.count.lines.map((line) => [line.id, { counted: line.counted, note: '' }]),
    ),
    note: '',
});

// Walk the store once: same grouping as the pack list.
const groups = computed(() => {
    const map = new Map();

    props.count.lines.forEach((line) => {
        const key = line.storage_location || 'Anywhere else';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(line);
    });

    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b));
});

function difference(line) {
    return Number((form.lines[line.id].counted - line.system).toFixed(3));
}

const changed = computed(() => props.count.lines.filter((line) => difference(line) !== 0));

function apply() {
    form.post(`/admin/stock/count/${props.count.id}/apply`);
}
</script>

<template>
    <AdminLayout :title="`Counting ${count.branch}`">
        <Head title="Count stock" />

        <Link
            href="/admin/stock"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Stock
        </Link>

        <p class="mb-4 max-w-2xl text-body text-ink-soft">
            Type what is actually on the shelf. Whatever you count becomes the new number, and the
            difference is written down with your reason - nothing is quietly overwritten.
        </p>

        <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
            <div class="space-y-4">
                <section
                    v-for="[location, lines] in groups"
                    :key="location"
                    class="overflow-hidden rounded-card border border-line bg-surface"
                >
                    <h2 class="flex items-center gap-2 border-b border-line bg-page px-card py-3 text-heading text-ink lg:px-card-lg">
                        <Warehouse :size="20" class="text-ink-soft" aria-hidden="true" />
                        {{ location }}
                    </h2>

                    <div class="divide-y divide-line">
                        <div
                            v-for="line in lines"
                            :key="line.id"
                            class="flex flex-wrap items-center gap-3 p-card lg:p-card-lg"
                            :class="difference(line) !== 0 ? 'bg-partial-bg/40' : ''"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-body text-ink">{{ line.item }}</p>
                                <p class="text-helper text-ink-soft">
                                    Books say <span class="tabular">{{ line.system_text }}</span>
                                    <span v-if="difference(line) !== 0" class="text-partial">
                                        · {{ difference(line) > 0 ? '+' : '' }}{{ difference(line) }}
                                        {{ line.unit }}
                                    </span>
                                </p>
                            </div>

                            <QtyStepper
                                v-model="form.lines[line.id].counted"
                                :step="line.step"
                                :decimals="line.decimals"
                                :unit="line.unit"
                                :label="line.item"
                            />
                        </div>
                    </div>
                </section>
            </div>

            <div class="lg:sticky lg:top-24 lg:self-start">
                <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                    <h2 class="text-heading text-ink">Finish the count</h2>

                    <p class="text-body text-ink">
                        <span class="tabular font-medium">{{ changed.length }}</span>
                        of {{ count.lines.length }} items are different.
                    </p>

                    <TextField
                        v-model="form.note"
                        label="Why are they different?"
                        hint="Everyone who looks at this later will read your answer."
                        :error="form.errors.note"
                    />

                    <AppButton
                        block
                        size="lg"
                        :disabled="!form.note"
                        :loading="form.processing"
                        loading-text="Applying…"
                        @click="apply"
                    >
                        Apply the count
                    </AppButton>

                    <p class="text-helper text-ink-soft">
                        This writes a correction for each difference. Nothing is deleted.
                    </p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
