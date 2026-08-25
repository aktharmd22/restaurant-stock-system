<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Package, Repeat, Search, StickyNote, X } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import TextField from '@/Components/ui/TextField.vue';
import { useOfflineQueue } from '@/Composables/useOfflineQueue';
import { useToast } from '@/Composables/useToast';
import { categoryColour } from '@/Support/categoryColours';

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
const onlyChosen = ref(false);

const { state: connection, enqueue } = useOfflineQueue();

/*
 * A token generated here, not on the server. If this send is queued and later
 * retried, the server recognises the token and hands back the request it
 * already made instead of making a second one.
 */
function newToken() {
    return typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `t-${Date.now()}-${Math.round(Math.random() * 1e9)}`;
}

const clientToken = ref(newToken());

const form = useForm({ lines: [], note: '', needed_by: null, client_token: clientToken.value });

/*
 * The screen opens already filled in: anything below its reorder level starts
 * at the suggested amount, so most days the job is to glance down and send.
 * Arriving from "Running low" fills in exactly those items instead.
 */
onMounted(() => {
    const prefill = new URLSearchParams(window.location.search).get('prefill');

    const wanted = prefill
        ? props.items.filter((item) => prefill.split(',').map(Number).includes(item.id))
        : props.items.filter((item) => item.is_low);

    wanted.forEach((item) => {
        quantities.value[item.id] = item.suggested > 0 ? item.suggested : item.step;
    });
});

const visibleItems = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.items.filter((item) => {
        const matchesCategory = !activeCategory.value || item.category_id === activeCategory.value;
        const matchesSearch = !term || item.name.toLowerCase().includes(term);
        const matchesChosen = !onlyChosen.value || (quantities.value[item.id] ?? 0) > 0;
        return matchesCategory && matchesSearch && matchesChosen;
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

/** Takes the item back out of the request entirely. */
function remove(item) {
    delete quantities.value[item.id];
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
    onlyChosen.value = false;
}

function send() {
    form.lines = chosen.value;
    form.client_token = clientToken.value;

    // Known to be offline: save it and say so, rather than spinning forever.
    if (!connection.online) {
        enqueue({
            url: '/b/ask',
            data: { lines: chosen.value, note: form.note, client_token: clientToken.value },
            label: 'Your stock request',
        });

        toast.success('No internet. We saved this — it will send when you are back online.', {
            duration: 9000,
        });

        quantities.value = {};
        form.note = '';
        clientToken.value = newToken();

        return;
    }

    form.post('/b/ask', { preserveScroll: true });
}
</script>

<template>
    <BranchLayout title="Ask for stock" back="/b">
        <Head title="Ask for stock" />

        <!-- The summary and the one action. The layout puts this in the header
             on a laptop and pins it above the thumb on a phone. -->
        <template #action>
            <div class="flex items-center gap-3">
                <div class="flex min-w-0 flex-1 items-center gap-1 lg:flex-none">
                    <p class="shrink-0 text-body font-medium text-ink">
                        {{ chosen.length }} item<span v-if="chosen.length !== 1">s</span>
                    </p>

                    <button
                        v-if="chosen.length"
                        type="button"
                        class="flex min-h-touch items-center gap-1 px-2 text-helper text-ink-soft transition hover:text-ink"
                        @click="clearAll"
                    >
                        <X :size="14" /> Start again
                    </button>
                    <button
                        v-else
                        type="button"
                        class="flex min-h-touch items-center gap-1 px-2 text-helper text-primary"
                        @click="noteOpen = true"
                    >
                        <StickyNote :size="14" /> Add a note
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

        <!-- Find things. Sticks to the top so it is always reachable. -->
        <div class="sticky top-[60px] z-10 -mx-4 border-b border-line bg-page px-4 pb-3 pt-3 lg:top-[64px] lg:-mx-6 lg:px-6">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[200px] flex-1">
                    <Search
                        :size="18"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted"
                    />
                    <input
                        v-model="search"
                        type="search"
                        inputmode="search"
                        placeholder="Find an item"
                        aria-label="Find an item"
                        class="min-h-control w-full rounded-control border border-line bg-surface pl-10 pr-3 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                    />
                </div>

                <button
                    v-if="lastTime?.length"
                    type="button"
                    class="flex min-h-control shrink-0 items-center gap-2 rounded-control border border-line bg-surface px-3 text-body text-ink transition hover:border-primary hover:text-primary"
                    @click="sameAsLastTime"
                >
                    <Repeat :size="16" />
                    <span class="hidden sm:inline">Same as last time</span>
                    <span class="sm:hidden">Last time</span>
                </button>

                <button
                    v-if="chosen.length"
                    type="button"
                    class="flex min-h-control shrink-0 items-center gap-2 rounded-control border px-3 text-body transition"
                    :class="
                        onlyChosen
                            ? 'border-primary bg-primary-light font-medium text-primary'
                            : 'border-line bg-surface text-ink'
                    "
                    @click="onlyChosen = !onlyChosen"
                >
                    <Check :size="16" />
                    Chosen ({{ chosen.length }})
                </button>
            </div>

            <div class="no-scrollbar mt-2 flex gap-2 overflow-x-auto">
                <button
                    type="button"
                    class="min-h-touch shrink-0 rounded-full border px-3.5 text-body transition"
                    :class="
                        activeCategory === null
                            ? 'border-primary bg-primary-light font-medium text-primary'
                            : 'border-line bg-surface text-ink-soft hover:text-ink'
                    "
                    @click="activeCategory = null"
                >
                    All
                </button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="flex min-h-touch shrink-0 items-center gap-2 rounded-full px-3.5 text-body transition"
                    :class="
                        activeCategory === category.id
                            ? `${categoryColour(category.colour).chip} font-medium ring-2 ring-inset ring-current`
                            : `${categoryColour(category.colour).chip} opacity-75 hover:opacity-100`
                    "
                    @click="activeCategory = activeCategory === category.id ? null : category.id"
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="categoryColour(category.colour).dot"
                        aria-hidden="true"
                    />
                    {{ category.name }}
                </button>
            </div>
        </div>

        <!-- The list. A grid on anything wider than a phone, so a kitchen sees
             a dozen items at once instead of scrolling through forty-eight. -->
        <div class="mt-4 grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <div
                v-for="item in visibleItems"
                :key="item.id"
                class="rounded-card p-3 transition"
                :class="[
                    categoryColour(item.colour).card,
                    qtyFor(item) > 0
                        ? `shadow-card ring-2 ring-inset ${categoryColour(item.colour).ring}`
                        : '',
                ]"
            >
                <!-- The name gets a row of its own. Sharing one with the
                     stepper truncated it on anything narrower than a table. -->
                <div class="flex items-center gap-2.5">
                    <img
                        v-if="item.photo"
                        :src="item.photo"
                        :alt="item.name"
                        loading="lazy"
                        class="h-10 w-10 shrink-0 rounded-control border border-line object-cover"
                    />
                    <span
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-surface/70"
                        :class="categoryColour(item.colour).text"
                        aria-hidden="true"
                    >
                        <Package :size="18" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-body font-medium text-ink">{{ item.name }}</p>
                        <p
                            class="truncate text-helper"
                            :class="item.is_low ? 'text-partial' : 'text-ink-soft'"
                        >
                            {{ item.on_hand_text }} left here
                        </p>
                    </div>

                    <span
                        v-if="item.is_low"
                        class="shrink-0 rounded-full bg-surface px-2 py-0.5 text-micro font-medium text-partial"
                    >
                        Low
                    </span>
                </div>

                <div class="mt-2.5 flex items-center justify-between gap-2">
                    <!-- Taking something back out should be one tap, not
                         winding the stepper down to nothing. -->
                    <button
                        v-if="qtyFor(item) > 0"
                        type="button"
                        class="-ml-2 flex min-h-touch min-w-0 items-center gap-1 rounded-control px-2 text-helper font-medium transition hover:bg-surface/70"
                        :class="categoryColour(item.colour).text"
                        @click="remove(item)"
                    >
                        <X :size="14" stroke-width="2.5" class="shrink-0" />
                        Remove
                    </button>
                    <span
                        v-else-if="item.suggested > 0"
                        class="truncate text-helper text-ink-soft"
                    >
                        Suggest {{ item.suggested }} {{ item.unit }}
                    </span>
                    <span v-else class="truncate text-helper text-ink-muted">Enough here</span>

                    <QtyStepper
                        class="bg-surface"
                        :model-value="qtyFor(item)"
                        :step="item.step"
                        :decimals="item.decimals"
                        :unit="item.unit"
                        :label="item.name"
                        @update:model-value="(value) => setQty(item, value)"
                    />
                </div>
            </div>
        </div>

        <p v-if="!visibleItems.length" class="py-10 text-center text-body text-ink-soft">
            <template v-if="onlyChosen">You have not chosen anything yet.</template>
            <template v-else>Nothing matches that. Try another word.</template>
        </p>

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
