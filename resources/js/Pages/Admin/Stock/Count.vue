<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Undo2, Warehouse } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import Card from '@/Components/ui/Card.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
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

const search = ref('');
const show = ref('all');

function difference(line) {
    return Number((form.lines[line.id].counted - line.system).toFixed(3));
}

const changed = computed(() => props.count.lines.filter((line) => difference(line) !== 0));

/*
 * Filtering only hides rows. Every line keeps its counted number in the form
 * whether or not it is on screen, so searching for one item halfway through a
 * count can never lose the forty already typed.
 */
const visible = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.count.lines.filter((line) => {
        const matchesTerm = !term || line.item.toLowerCase().includes(term);
        const matchesShow = show.value === 'all' || difference(line) !== 0;

        return matchesTerm && matchesShow;
    });
});

// Walk the store once: same grouping as the pack list.
const groups = computed(() => {
    const map = new Map();

    visible.value.forEach((line) => {
        const key = line.storage_location || 'Anywhere else';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(line);
    });

    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b));
});

const filtering = computed(() => search.value !== '' || show.value !== 'all');

function clearFilters() {
    search.value = '';
    show.value = 'all';
}

function resetLine(line) {
    form.lines[line.id].counted = line.system;
}

function apply() {
    form.post(`/admin/stock/count/${props.count.id}/apply`);
}
</script>

<template>
    <AdminLayout
        :title="`Counting ${count.branch}`"
        subtitle="Type what is actually on the shelf. The difference is written down with your reason — nothing is quietly overwritten."
    >
        <Head title="Count stock" />

        <Link
            href="/admin/stock"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="18" />
            Stock
        </Link>

        <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
            <div>
                <!-- Sixty items is a long walk. Finding one of them should not
                     mean scrolling past the other fifty-nine. -->
                <Card class="mb-4">
                    <div class="grid items-end gap-3 sm:grid-cols-2">
                        <SearchField v-model="search" placeholder="Item name" />
                        <SelectField
                            v-model="show"
                            label="Show"
                            :options="[
                                { value: 'all', label: `Everything (${count.lines.length})` },
                                { value: 'changed', label: `Only what is different (${changed.length})` },
                            ]"
                        />
                    </div>
                </Card>

                <div v-if="visible.length" class="space-y-4">
                    <Card v-for="[location, lines] in groups" :key="location" :padded="false">
                        <h2
                            class="flex items-center gap-2 border-b border-line bg-page px-4 py-2.5 text-helper font-medium uppercase tracking-wide text-ink-soft sm:px-5"
                        >
                            <Warehouse :size="14" aria-hidden="true" />
                            {{ location }}
                            <span class="font-normal normal-case tracking-normal text-ink-muted">
                                · {{ lines.length }}
                            </span>
                        </h2>

                        <div class="divide-y divide-line">
                            <div
                                v-for="line in lines"
                                :key="line.id"
                                class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5"
                                :class="difference(line) !== 0 ? 'bg-partial-bg/40' : ''"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-body text-ink">{{ line.item }}</p>
                                    <p class="text-helper text-ink-soft">
                                        Books say <span class="tabular">{{ line.system_text }}</span>
                                        <span v-if="difference(line) !== 0" class="font-medium text-partial">
                                            · {{ difference(line) > 0 ? '+' : '' }}{{ difference(line) }}
                                            {{ line.unit }}
                                        </span>
                                    </p>
                                </div>

                                <button
                                    v-if="difference(line) !== 0"
                                    type="button"
                                    class="flex min-h-touch items-center gap-1 px-2 text-helper text-primary"
                                    @click="resetLine(line)"
                                >
                                    <Undo2 :size="14" />
                                    Undo
                                </button>

                                <QtyStepper
                                    v-model="form.lines[line.id].counted"
                                    :step="line.step"
                                    :decimals="line.decimals"
                                    :unit="line.unit"
                                    :label="line.item"
                                />
                            </div>
                        </div>
                    </Card>
                </div>

                <Card v-else>
                    <p class="py-6 text-center text-body text-ink-soft">
                        <template v-if="show === 'changed'">
                            Nothing is different yet. Count something and it will show up here.
                        </template>
                        <template v-else>Nothing matches that. Try another word.</template>
                    </p>
                    <div v-if="filtering" class="text-center">
                        <AppButton variant="secondary" @click="clearFilters">
                            Show everything
                        </AppButton>
                    </div>
                </Card>
            </div>

            <div class="lg:sticky lg:top-24 lg:self-start">
                <Card title="Finish the count">
                    <div class="space-y-4">
                        <p class="text-body text-ink">
                            <span class="tabular font-medium">{{ changed.length }}</span>
                            of {{ count.lines.length }} items are different.
                        </p>

                        <button
                            v-if="changed.length && show === 'all'"
                            type="button"
                            class="flex min-h-touch items-center text-body font-medium text-primary"
                            @click="show = 'changed'"
                        >
                            Show me just those
                        </button>

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
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
