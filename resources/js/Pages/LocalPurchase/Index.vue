<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Plus, ShoppingCart } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    purchases: { type: Object, required: true },
    items: { type: Array, required: true },
    canDecide: { type: Boolean, default: false },
    canRequest: { type: Boolean, default: false },
    currency: { type: String, default: '₹' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const Layout = computed(() => (user.value.is_admin_side ? AdminLayout : BranchLayout));

const sheetOpen = ref(false);
const rejecting = ref(null);
const rejectNote = ref('');

const form = useForm({ item_id: '', qty: 1, amount: '', reason: '', bill: null });

const tone = { waiting: 'waiting', approved: 'approved', rejected: 'rejected' };
const label = { waiting: 'Waiting', approved: 'Approved', rejected: 'Not approved' };

function pickBill(event) {
    form.bill = event.target.files?.[0] ?? null;
}

function save() {
    form.post('/local-purchases', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            sheetOpen.value = false;
        },
    });
}

function approve(purchase) {
    router.post(`/local-purchases/${purchase.id}/approve`, {}, { preserveScroll: true });
}

function reject() {
    router.post(
        `/local-purchases/${rejecting.value.id}/reject`,
        { note: rejectNote.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejecting.value = null;
                rejectNote.value = '';
            },
        },
    );
}
</script>

<template>
    <component :is="Layout" title="Bought locally" :back="user.is_admin_side ? undefined : '/b/more'">
        <Head title="Bought locally" />

        <template v-if="canRequest" #header-action>
            <AppButton @click="sheetOpen = true">
                <template #icon><Plus :size="20" /></template>
                Add a bill
            </AppButton>
        </template>

        <p class="mb-4 max-w-2xl text-body text-ink-soft">
            When a branch has to buy something itself, record it here with a photo of the bill. Stock
            only moves once the main store approves it.
        </p>

        <div v-if="purchases.data.length" class="space-y-2">
            <SpineCard v-for="purchase in purchases.data" :key="purchase.id" :status="tone[purchase.status]">
                <div class="p-card">
                    <div class="flex items-start gap-3">
                        <a
                            v-if="purchase.bill"
                            :href="purchase.bill"
                            target="_blank"
                            rel="noopener"
                            class="shrink-0"
                        >
                            <img
                                :src="purchase.bill"
                                alt="Bill"
                                loading="lazy"
                                class="h-14 w-14 rounded-control border border-line object-cover"
                            />
                        </a>
                        <span
                            v-else
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-control bg-page text-ink-muted"
                        >
                            <ShoppingCart :size="20" aria-hidden="true" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-body font-medium text-ink">
                                {{ purchase.item }} · <span class="tabular">{{ purchase.qty_text }}</span>
                                <span class="tabular text-ink-soft">
                                    · {{ currency }}{{ purchase.amount.toLocaleString('en-IN') }}
                                </span>
                            </p>
                            <p class="text-helper text-ink-soft">{{ purchase.reason }}</p>
                            <p class="text-helper text-ink-muted">
                                {{ purchase.when }} · {{ purchase.by }}
                                <span v-if="purchase.branch && user.is_admin_side"> · {{ purchase.branch }}</span>
                            </p>
                            <p v-if="purchase.decision_note" class="mt-1 text-helper text-partial">
                                {{ purchase.decision_note }}
                            </p>
                        </div>

                        <StatusPill :status="tone[purchase.status]" :label="label[purchase.status]" />
                    </div>

                    <div v-if="canDecide && purchase.status === 'waiting'" class="mt-3 flex flex-wrap gap-2">
                        <AppButton @click="approve(purchase)">Approve and add to stock</AppButton>
                        <AppButton variant="danger" @click="rejecting = purchase">Not approved</AppButton>
                    </div>
                </div>
            </SpineCard>

            <Pagination :links="purchases.links" :meta="purchases" />
        </div>

        <EmptyState
            v-else
            icon="ShoppingCart"
            title="Nothing bought locally"
            message="Emergency buys show up here with their bills."
        >
            <template v-if="canRequest" #action>
                <AppButton @click="sheetOpen = true">Add a bill</AppButton>
            </template>
        </EmptyState>

        <!-- Branch: record a purchase -->
        <BottomSheet
            :open="sheetOpen"
            title="Add a bill"
            description="The main store checks it before it counts as stock."
            @close="sheetOpen = false"
        >
            <div class="space-y-4">
                <SelectField
                    v-model="form.item_id"
                    label="What you bought"
                    placeholder="Pick an item"
                    :options="items.map((item) => ({ value: item.id, label: item.name }))"
                    :error="form.errors.item_id"
                />

                <TextField
                    v-model="form.qty"
                    label="How much"
                    inputmode="decimal"
                    :error="form.errors.qty"
                />

                <TextField
                    v-model="form.amount"
                    :label="`What you paid (${currency})`"
                    inputmode="decimal"
                    :error="form.errors.amount"
                />

                <TextField
                    v-model="form.reason"
                    label="Why you had to buy it"
                    :error="form.errors.reason"
                />

                <label class="inline-flex min-h-touch cursor-pointer items-center gap-2 rounded-control border border-line px-4 text-body text-ink">
                    <Camera :size="20" />
                    {{ form.bill ? 'Bill photo added' : 'Photo of the bill' }}
                    <input type="file" accept="image/*" capture="environment" class="sr-only" @change="pickBill" />
                </label>
            </div>

            <template #footer>
                <AppButton block size="lg" :loading="form.processing" loading-text="Sending…" @click="save">
                    Send to main store
                </AppButton>
            </template>
        </BottomSheet>

        <!-- Admin: refuse, with a reason the branch can act on -->
        <BottomSheet
            :open="rejecting !== null"
            title="Not approved"
            description="Say why, so the branch knows what to do next time."
            @close="rejecting = null"
        >
            <TextField v-model="rejectNote" label="Reason" />

            <template #footer>
                <AppButton variant="danger" block size="lg" :disabled="!rejectNote" @click="reject">
                    Save reason
                </AppButton>
            </template>
        </BottomSheet>
    </component>
</template>
