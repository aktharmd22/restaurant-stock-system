<script setup>
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import QtyStepper from '@/Components/ui/QtyStepper.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';
import TextField from '@/Components/ui/TextField.vue';
import { useToast } from '@/Composables/useToast';
import { STATUS } from '@/Support/status';

const page = usePage();
const Layout = computed(() => (page.props.auth?.user?.is_admin_side ? AdminLayout : BranchLayout));

const toast = useToast();
const sheetOpen = ref(false);
const qty = ref(4);
const sample = ref('');

const statuses = Object.keys(STATUS);

const swatches = [
    { name: 'Page', className: 'bg-page', hex: '#F5F6F8' },
    { name: 'Surface', className: 'bg-surface border border-line', hex: '#FFFFFF' },
    { name: 'Border', className: 'bg-line', hex: '#ECEEF2' },
    { name: 'Text', className: 'bg-ink', hex: '#16181D' },
    { name: 'Text soft', className: 'bg-ink-soft', hex: '#6B7280' },
    { name: 'Text muted', className: 'bg-ink-muted', hex: '#9CA3AF' },
    { name: 'Primary', className: 'bg-primary', hex: '#1E3A8A' },
    { name: 'Primary light', className: 'bg-primary-light', hex: '#E9EDF9' },
    { name: 'Waiting', className: 'bg-waiting', hex: '#B45309' },
    { name: 'Approved', className: 'bg-approved', hex: '#15803D' },
    { name: 'Partial', className: 'bg-partial', hex: '#C2410C' },
    { name: 'Rejected', className: 'bg-rejected', hex: '#B91C1C' },
    { name: 'Sidebar', className: 'bg-shell', hex: '#0F1D40' },
];
</script>

<template>
    <component :is="Layout" title="Design reference">
        <Head title="Design reference" />

        <div class="space-y-6">
            <!-- Type scale -->
            <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <h2 class="text-heading text-ink">Type</h2>
                <div class="mt-4 space-y-3">
                    <p class="text-title text-ink">Page title · 20px bold</p>
                    <p class="text-heading text-ink">Section heading · 16px semibold</p>
                    <p class="text-body text-ink">Body and labels · 14px regular</p>
                    <p class="text-qty tabular text-ink">12.5 kg · quantity · 18px semibold</p>
                    <p class="text-stat tabular text-ink">248 · big number · 28px bold</p>
                    <p class="text-helper text-ink-soft">Helper text · 13px</p>
                </div>
            </section>

            <!-- Colour -->
            <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <h2 class="text-heading text-ink">Colour</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div v-for="swatch in swatches" :key="swatch.name">
                        <div class="h-12 rounded-control" :class="swatch.className" />
                        <p class="mt-1.5 text-helper text-ink">{{ swatch.name }}</p>
                        <p class="text-helper tabular text-ink-muted">{{ swatch.hex }}</p>
                    </div>
                </div>
            </section>

            <!-- Status: colour + icon + word, never colour alone -->
            <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <h2 class="text-heading text-ink">Status</h2>
                <p class="mt-1 text-helper text-ink-soft">
                    Every status shows a colour, an icon and a word.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <StatusPill v-for="status in statuses" :key="status" :status="status" />
                </div>
            </section>

            <!-- The signature element -->
            <section>
                <h2 class="mb-1 text-heading text-ink">Row cards</h2>
                <p class="mb-3 text-helper text-ink-soft">
                    The spine down the left edge carries the status. Readable from arm's length.
                </p>

                <div class="space-y-2">
                    <SpineCard v-for="status in ['waiting', 'approved', 'partial', 'rejected', 'sent']" :key="status" :status="status">
                        <div class="flex items-center justify-between gap-3 p-card">
                            <div>
                                <p class="text-body font-medium text-ink">Wednesday, 12 items</p>
                                <p class="text-helper text-ink-soft">Sent 9:14 am</p>
                            </div>
                            <StatusPill :status="status" />
                        </div>
                    </SpineCard>
                </div>
            </section>

            <!-- Numbers -->
            <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <h2 class="text-heading text-ink">Quantity</h2>
                <p class="mt-1 text-helper text-ink-soft">
                    44px targets. Hold to run up fast. Tap the number to type it.
                </p>
                <div class="mt-4">
                    <QtyStepper v-model="qty" unit="kg" :step="0.5" label="Chicken" />
                </div>
            </section>

            <!-- Stats -->
            <section>
                <h2 class="mb-3 text-heading text-ink">Numbers at a glance</h2>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard label="Waiting for you" :value="7" icon="Clock" tone="waiting" />
                    <StatCard label="To send today" :value="3" icon="Truck" tone="primary" />
                    <StatCard label="In transit" :value="2" icon="MapPin" />
                    <StatCard label="Low stock" :value="11" icon="TrendingDown" tone="partial" />
                </div>
            </section>

            <!-- Controls -->
            <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <h2 class="text-heading text-ink">Buttons and fields</h2>

                <div class="mt-4 flex flex-wrap gap-3">
                    <AppButton>
                        <template #icon><Plus :size="20" /></template>
                        Send request
                    </AppButton>
                    <AppButton variant="secondary">Cancel</AppButton>
                    <AppButton variant="ghost">Same as last time</AppButton>
                    <AppButton variant="danger">Not approved</AppButton>
                    <AppButton loading loading-text="Signing in…">Sign in</AppButton>
                </div>

                <div class="mt-4 max-w-sm space-y-3">
                    <TextField v-model="sample" label="Phone number or email" inputmode="tel" />
                    <TextField
                        v-model="sample"
                        label="Password"
                        type="password"
                        error="That password does not match. Try again."
                    />
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <AppButton variant="secondary" @click="sheetOpen = true">Open a sheet</AppButton>
                    <AppButton
                        variant="secondary"
                        @click="toast.success('Request sent', { action: { label: 'Undo', onClick: () => toast.info('Request cancelled') } })"
                    >
                        Show a message
                    </AppButton>
                </div>
            </section>

            <!-- Waiting and nothing -->
            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                    <h2 class="text-heading text-ink">Loading</h2>
                    <div class="mt-4 space-y-3">
                        <Skeleton height="20px" width="60%" />
                        <Skeleton height="16px" width="40%" />
                        <Skeleton height="72px" rounded="14px" />
                    </div>
                </div>

                <EmptyState
                    icon="ClipboardList"
                    title="No requests yet"
                    message="Tap below to ask for stock."
                >
                    <template #action>
                        <AppButton>Ask for stock</AppButton>
                    </template>
                </EmptyState>
            </section>
        </div>

        <BottomSheet
            :open="sheetOpen"
            title="Why less than asked?"
            description="Pick a reason. The branch sees this."
            @close="sheetOpen = false"
        >
            <div class="space-y-2">
                <button
                    v-for="reason in ['Out of stock', 'Too much asked', 'Not needed today', 'Other']"
                    :key="reason"
                    type="button"
                    class="flex min-h-touch w-full items-center rounded-control border border-line px-4 text-body text-ink hover:border-primary hover:bg-primary-light"
                >
                    {{ reason }}
                </button>
            </div>

            <template #footer>
                <AppButton block size="lg" @click="sheetOpen = false">Save reason</AppButton>
            </template>
        </BottomSheet>
    </component>
</template>
