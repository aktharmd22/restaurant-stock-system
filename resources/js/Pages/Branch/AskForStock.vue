<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Repeat, Search, X } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import TextField from '@/Components/ui/TextField.vue';
import { useToast } from '@/Composables/useToast';

const props = defineProps({
    items: { type: Array, required: true },
    categories: { type: Array, required: true },
    cutoff: { type: Object, required: true },
    lastTime: { type: Array, default: null },
});

const toast = useToast();

const quantities = ref({});
const activeCategory = ref(null);
const search = ref('');
const noteOpen = ref(false);

const form = useForm({ lines: [], note: '', needed_by: null });

/*
 * Arriving from "Running low" pre-fills those items with the suggested amount,
 * so the user lands on a form that is already right.
 */
onMounted(() => {
    const prefill = new URLSearchParams(window.location.search).get('prefill');
    if (!prefill) return;

    const ids = prefill.split(',').map(Number).filter(Boolean);

    props.items
        .filter((item) => ids.includes(item.id))
        .forEach((item) => {
            quantities.value[item.id] = item.suggested > 0 ? item.suggested : item.step;
        });
});

const visibleItems = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.items.filter((item) => {
        const matchesCategory = !activeCategory.value || item.category_id === activeCategory.value;
        const matchesSearch = !term || item.name.toLowerCase().includes(term);
        return matchesCategory && matchesSearch;
    });
});

const chosen = computed(() =>
    props.items
        .filter((item) => (quantities.value[item.id] ?? 0) > 0)
        .map((item) => ({ item_id: item.id, qty: quantities.value[item.id] })),
);

function qtyFor(item) {
    return quantities.value[item.id] ?? 0;
}

function setQty(item, value) {
    quantities.value[item.id] = value;
}

/** Most days a kitchen orders close to the same thing. */
function sameAsLastTime() {
    if (!props.lastTime?.length) return;

    props.lastTime.forEach((line) => {
        quantities.value[line.item_id] = line.qty;
    });

    toast.info('Filled in from your last request. Change anything you need.');
}

function clearAll() {
    quantities.value = {};
}

function send() {
    form.lines = chosen.value;
    form.post('/b/ask', { preserveScroll: true });
}
</script>

<template>
    <BranchLayout title="Ask for stock" back="/b">
        <Head title="Ask for stock" />

        <!-- Search and groups stay put while the list scrolls under them -->
        <div class="sticky top-[60px] -mx-4 -mt-4 border-b border-line bg-page px-4 pb-3 pt-4">
            <div class="relative">
                <Search :size="20" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-muted" />
                <input
                    v-model="search"
                    type="search"
                    inputmode="search"
                    placeholder="Find an item"
                    aria-label="Find an item"
                    class="min-h-control w-full rounded-control border border-line bg-surface pl-12 pr-4 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                />
            </div>

            <div class="no-scrollbar mt-3 flex gap-2 overflow-x-auto">
                <button
                    type="button"
                    class="min-h-touch shrink-0 rounded-full border px-4 text-body transition"
                    :class="
                        activeCategory === null
                            ? 'border-primary bg-primary-light font-medium text-primary'
                            : 'border-line bg-surface text-ink-soft'
                    "
                    @click="activeCategory = null"
                >
                    All
                </button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="min-h-touch shrink-0 rounded-full border px-4 text-body transition"
                    :class="
                        activeCategory === category.id
                            ? 'border-primary bg-primary-light font-medium text-primary'
                            : 'border-line bg-surface text-ink-soft'
                    "
                    @click="activeCategory = activeCategory === category.id ? null : category.id"
                >
                    {{ category.name }}
                </button>
            </div>
        </div>

        <div v-if="lastTime?.length" class="mt-4">
            <AppButton variant="secondary" block @click="sameAsLastTime">
                <template #icon><Repeat :size="20" /></template>
                Same as last time
            </AppButton>
        </div>

        <!-- The list -->
        <div class="mt-4 space-y-2">
            <div
                v-for="item in visibleItems"
                :key="item.id"
                class="rounded-card border bg-surface p-card"
                :class="qtyFor(item) > 0 ? 'border-primary' : 'border-line'"
            >
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-body font-medium text-ink">{{ item.name }}</p>
                        <p class="text-helper" :class="item.is_low ? 'text-partial' : 'text-ink-soft'">
                            {{ item.on_hand_text }} left here
                        </p>
                        <p v-if="item.suggested > 0 && qtyFor(item) === 0" class="mt-1 text-helper text-ink-muted">
                            Suggested {{ item.suggested }} {{ item.unit }}
                        </p>
                    </div>

                    <QtyStepper
                        :model-value="qtyFor(item)"
                        :step="item.step"
                        :decimals="item.decimals"
                        :unit="item.unit"
                        :label="item.name"
                        @update:model-value="(value) => setQty(item, value)"
                    />
                </div>
            </div>

            <p v-if="!visibleItems.length" class="py-8 text-center text-body text-ink-soft">
                Nothing matches that. Try another word.
            </p>
        </div>

        <!-- Sticky footer: what is chosen, and the one action -->
        <template #action>
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-body font-medium text-ink">
                        {{ chosen.length }} item<span v-if="chosen.length !== 1">s</span>
                    </p>
                    <button
                        v-if="chosen.length"
                        type="button"
                        class="inline-flex items-center gap-1 text-helper text-ink-soft"
                        @click="clearAll"
                    >
                        <X :size="16" /> Start again
                    </button>
                    <button
                        v-else
                        type="button"
                        class="text-helper text-primary"
                        @click="noteOpen = true"
                    >
                        Add a note
                    </button>
                </div>

                <AppButton
                    size="lg"
                    :disabled="!chosen.length"
                    :loading="form.processing"
                    loading-text="Sending…"
                    @click="send"
                >
                    Send request
                </AppButton>
            </div>
        </template>

        <BottomSheet
            :open="noteOpen"
            title="Add a note"
            description="Anything the main store should know about this request."
            @close="noteOpen = false"
        >
            <TextField v-model="form.note" label="Note (optional)" />

            <template #footer>
                <AppButton block size="lg" @click="noteOpen = false">Save note</AppButton>
            </template>
        </BottomSheet>
    </BranchLayout>
</template>
