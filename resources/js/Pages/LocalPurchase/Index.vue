<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Plus, Receipt } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ListRow from '@/Components/ui/ListRow.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import StatusText from '@/Components/ui/StatusText.vue';
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

// Anything still waiting is the only thing here that needs a decision.
const waiting = computed(() => props.purchases.data.filter((p) => p.status === 'waiting'));
const decided = computed(() => props.purchases.data.filter((p) => p.status !== 'waiting'));

// Paise only show when there are any, so a round bill is not "₹42.00".
const money = (value) => {
    const amount = Number(value);
    return `${props.currency}${amount.toLocaleString('en-IN', {
        minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
        maximumFractionDigits: 2,
    })}`;
};

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
    <component
        :is="Layout"
        title="Bought locally"
        subtitle="Emergency buys. Stock only moves once the main store approves."
        :back="user.is_admin_side ? undefined : '/b/more'"
    >
        <Head title="Bought locally" />

        <template v-if="canRequest" #action>
            <AppButton size="lg" class="w-full lg:w-auto" @click="sheetOpen = true">
                <template #icon><Plus :size="16" /></template>
                Add a bill
            </AppButton>
        </template>

        <template v-if="purchases.data.length">
            <!-- Needs a decision. Actions live on the row, not in a card each. -->
            <Card v-if="waiting.length" :padded="false" :title="`${waiting.length} waiting on you`">
                <div class="divide-y divide-line">
                    <div v-for="purchase in waiting" :key="purchase.id" class="px-4 py-3 sm:px-5">
                        <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
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
                                    class="h-10 w-10 rounded-control border border-line object-cover"
                                />
                            </a>
                            <span
                                v-else
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-page text-ink-muted"
                            >
                                <Receipt :size="16" aria-hidden="true" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-body text-ink">
                                    <span class="font-medium">{{ purchase.item }}</span>
                                    <span class="text-ink-soft"> · {{ purchase.qty_text }}</span>
                                </p>
                                <p class="truncate text-helper text-ink-soft">{{ purchase.reason }}</p>
                                <p class="truncate text-helper text-ink-muted">
                                    {{ purchase.when }} · {{ purchase.by }}
                                    <span v-if="purchase.branch && user.is_admin_side">· {{ purchase.branch }}</span>
                                </p>
                            </div>

                            <p class="shrink-0 text-qty tabular text-ink">{{ money(purchase.amount) }}</p>
                        </div>

                        <div v-if="canDecide" class="mt-2.5 flex flex-wrap gap-2 pl-14">
                            <AppButton @click="approve(purchase)">Approve and add to stock</AppButton>
                            <AppButton variant="ghost" @click="rejecting = purchase">Not approved</AppButton>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Already decided. Quiet, because nothing here needs doing. -->
            <Card v-if="decided.length" class="mt-4" :padded="false" title="Already decided">
                <div class="divide-y divide-line">
                    <ListRow
                        v-for="purchase in decided"
                        :key="purchase.id"
                        :status="tone[purchase.status]"
                        :chevron="false"
                    >
                        <span class="block truncate text-body text-ink">
                            <span class="font-medium">{{ purchase.item }}</span>
                            <span class="text-ink-soft"> · {{ purchase.qty_text }}</span>
                        </span>
                        <span class="mt-0.5 block truncate text-helper text-ink-muted">
                            {{ purchase.when }} · {{ purchase.by }}
                            <span v-if="purchase.branch && user.is_admin_side">· {{ purchase.branch }}</span>
                            <span v-if="purchase.decision_note"> · {{ purchase.decision_note }}</span>
                        </span>

                        <template #end>
                            <span class="w-20 text-right text-body tabular text-ink">
                                {{ money(purchase.amount) }}
                            </span>
                            <span class="flex w-[116px] justify-end">
                                <StatusText
                                    :status="tone[purchase.status]"
                                    :label="label[purchase.status]"
                                    size="sm"
                                />
                            </span>
                        </template>
                    </ListRow>
                </div>
            </Card>

            <Pagination :links="purchases.links" :meta="purchases" />
        </template>

        <EmptyState
            v-else
            icon="ShoppingCart"
            title="Nothing bought locally"
            message="When a branch has to buy something itself, it lands here with a photo of the bill."
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

                <TextField v-model="form.qty" label="How much" inputmode="decimal" :error="form.errors.qty" />

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
                    <Camera :size="18" />
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
