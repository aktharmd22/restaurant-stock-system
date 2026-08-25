<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Plus, Trash2 } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import Card from '@/Components/ui/Card.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    entries: { type: Object, required: true },
    items: { type: Array, required: true },
    reasons: { type: Array, required: true },
    branches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const Layout = computed(() => (user.value.is_admin_side ? AdminLayout : BranchLayout));

const sheetOpen = ref(false);

const search = ref(props.filters.search ?? '');
const reason = ref(props.filters.reason ?? '');
const branchFilter = ref(props.filters.branch ?? '');

let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => reload({ search: value || undefined }), 300);
});

watch([reason, branchFilter], () => reload());

function reload(extra = {}) {
    router.get(
        '/waste',
        {
            search: search.value || undefined,
            reason: reason.value || undefined,
            branch: branchFilter.value || undefined,
            ...extra,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const form = useForm({
    item_id: '',
    qty: 0,
    reason: null,
    note: '',
    branch_id: props.filters.branch ?? null,
    photo: null,
});

const chosenItem = computed(() => props.items.find((item) => item.id === Number(form.item_id)));

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const item = Number(params.get('item'));

    if (item && props.items.some((candidate) => candidate.id === item)) {
        form.item_id = item;
        sheetOpen.value = true;
    }
});

function pickPhoto(event) {
    form.photo = event.target.files?.[0] ?? null;
}

function save() {
    form.post('/waste', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('item_id', 'qty', 'reason', 'note', 'photo');
            sheetOpen.value = false;
        },
    });
}
</script>

<template>
    <component :is="Layout" title="Thrown away" :back="user.is_admin_side ? undefined : '/b/more'">
        <Head title="Thrown away" />

        <template #header-action>
            <AppButton @click="sheetOpen = true">
                <template #icon><Plus :size="20" /></template>
                Record waste
            </AppButton>
        </template>

        <!-- Every question this screen gets asked, in one row. -->
        <Card class="mb-4">
            <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <SearchField v-model="search" placeholder="Item name" />

                <SelectField
                    v-model="reason"
                    label="What happened"
                    placeholder="Any reason"
                    :options="reasons"
                />

                <SelectField
                    v-if="branches.length"
                    v-model="branchFilter"
                    label="Branch"
                    :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                />
            </div>
        </Card>

        <div v-if="entries.data.length">
            <Card :padded="false">
                <div class="divide-y divide-line">
                    <div
                        v-for="entry in entries.data"
                        :key="entry.id"
                        class="flex items-start gap-3 px-4 py-3 sm:px-5"
                    >
                        <img
                            v-if="entry.photo"
                            :src="entry.photo"
                            alt=""
                            loading="lazy"
                            class="h-10 w-10 shrink-0 rounded-control border border-line object-cover"
                        />
                        <span
                            v-else
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-rejected-bg text-rejected"
                        >
                            <Trash2 :size="16" aria-hidden="true" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-body text-ink">
                                <span class="font-medium">{{ entry.item }}</span>
                                <span class="text-ink-soft"> · </span>
                                <span class="tabular">{{ entry.qty_text }}</span>
                            </p>
                            <p class="truncate text-helper text-ink-soft">
                                {{ entry.reason }}<span v-if="entry.note"> · {{ entry.note }}</span>
                            </p>
                            <p class="truncate text-helper text-ink-muted">
                                {{ entry.when }} · {{ entry.by }}
                                <span v-if="entry.branch && user.is_admin_side"> · {{ entry.branch }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </Card>

            <Pagination :links="entries.links" :meta="entries" />
        </div>

        <EmptyState
            v-else
            icon="Trash2"
            :title="search || reason ? 'Nothing matches that' : 'Nothing thrown away yet'"
            :message="
                search || reason
                    ? 'Try a different word, or clear what you picked.'
                    : 'Recording waste keeps your stock numbers honest, and shows where money is going.'
            "
        >
            <template #action>
                <AppButton @click="sheetOpen = true">Record waste</AppButton>
            </template>
        </EmptyState>

        <BottomSheet
            :open="sheetOpen"
            title="Record waste"
            description="This takes the amount off your stock straight away."
            @close="sheetOpen = false"
        >
            <div class="space-y-4">
                <SelectField
                    v-if="branches.length"
                    v-model="form.branch_id"
                    label="Branch"
                    :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                />

                <SelectField
                    v-model="form.item_id"
                    label="What was thrown away"
                    placeholder="Pick an item"
                    :options="items.map((item) => ({ value: item.id, label: `${item.name} (${item.on_hand_text} left)` }))"
                    :error="form.errors.item_id"
                />

                <div v-if="chosenItem">
                    <p class="mb-2 text-helper text-ink-soft">How much</p>
                    <QtyStepper
                        v-model="form.qty"
                        :step="chosenItem.step"
                        :decimals="chosenItem.decimals"
                        :unit="chosenItem.unit"
                        label="Amount"
                    />
                    <p v-if="form.errors.qty" class="mt-2 text-helper text-rejected">{{ form.errors.qty }}</p>
                </div>

                <div>
                    <p class="mb-2 text-helper text-ink-soft">What happened</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="reason in reasons"
                            :key="reason.value"
                            type="button"
                            class="min-h-touch rounded-full border px-4 text-body transition"
                            :class="
                                form.reason === reason.value
                                    ? 'border-rejected bg-rejected-bg font-medium text-rejected'
                                    : 'border-line bg-surface text-ink-soft'
                            "
                            @click="form.reason = reason.value"
                        >
                            {{ reason.label }}
                        </button>
                    </div>
                    <p v-if="form.errors.reason" class="mt-2 text-helper text-rejected">{{ form.errors.reason }}</p>
                </div>

                <TextField v-model="form.note" label="Anything to add (optional)" />

                <label class="inline-flex min-h-touch cursor-pointer items-center gap-2 rounded-control border border-line px-4 text-body text-ink">
                    <Camera :size="20" />
                    {{ form.photo ? 'Photo added' : 'Add a photo' }}
                    <input type="file" accept="image/*" capture="environment" class="sr-only" @change="pickPhoto" />
                </label>
            </div>

            <template #footer>
                <AppButton
                    block
                    size="lg"
                    :disabled="!form.item_id || !form.qty || !form.reason"
                    :loading="form.processing"
                    loading-text="Saving…"
                    @click="save"
                >
                    Record waste
                </AppButton>
            </template>
        </BottomSheet>
    </component>
</template>
