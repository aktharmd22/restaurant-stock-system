<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Phone, Plus, Power, Trash2 } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import Card from '@/Components/ui/Card.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import RowMenu from '@/Components/ui/RowMenu.vue';
import RowMenuItem from '@/Components/ui/RowMenuItem.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    suppliers: { type: Array, required: true },
});

const search = ref('');
const deleting = ref(null);

const shown = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.suppliers;

    return props.suppliers.filter(
        (supplier) =>
            supplier.name.toLowerCase().includes(term) ||
            (supplier.contact_person ?? '').toLowerCase().includes(term) ||
            (supplier.phone ?? '').includes(term),
    );
});

function remove() {
    router.delete(`/admin/suppliers/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

const sheetOpen = ref(false);
const editing = ref(null);

const form = useForm({ name: '', contact_person: '', phone: '', address: '' });

function openNew() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEdit(supplier) {
    editing.value = supplier;
    form.name = supplier.name;
    form.contact_person = supplier.contact_person ?? '';
    form.phone = supplier.phone ?? '';
    form.address = supplier.address ?? '';
    form.clearErrors();
    sheetOpen.value = true;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (sheetOpen.value = false) };

    editing.value
        ? form.put(`/admin/suppliers/${editing.value.id}`, options)
        : form.post('/admin/suppliers', options);
}

function toggle(supplier) {
    router.post(`/admin/suppliers/${supplier.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Suppliers">
        <Head title="Suppliers" />

        <template #header-action>
            <AppButton @click="openNew">
                <template #icon><Plus :size="20" /></template>
                Add supplier
            </AppButton>
        </template>

        <Link
            href="/admin/purchase"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Purchase
        </Link>

        <Card>
            <SearchField v-model="search" placeholder="Supplier, contact or phone" />
        </Card>

        <Card v-if="shown.length" class="mt-4" :padded="false">
            <div class="divide-y divide-line">
                <div
                    v-for="supplier in shown"
                    :key="supplier.id"
                    class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5"
                    :class="supplier.is_active ? '' : 'opacity-60'"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-body font-medium text-ink">
                            {{ supplier.name }}
                            <span v-if="!supplier.is_active" class="text-helper text-ink-muted">
                                · switched off
                            </span>
                        </p>
                        <p class="truncate text-helper text-ink-soft">
                            {{ supplier.contact_person ?? 'No contact name' }}
                            <span v-if="supplier.address"> · {{ supplier.address }}</span>
                            · {{ supplier.orders }} orders
                        </p>
                        <a
                            v-if="supplier.phone"
                            :href="`tel:${supplier.phone}`"
                            class="inline-flex min-h-touch items-center gap-2 text-body text-primary"
                        >
                            <Phone :size="16" />
                            {{ supplier.phone }}
                        </a>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <AppButton variant="secondary" @click="openEdit(supplier)">Edit</AppButton>

                        <RowMenu :label="`More for ${supplier.name}`">
                            <RowMenuItem @click="toggle(supplier)">
                                <template #icon><Power :size="16" /></template>
                                {{ supplier.is_active ? 'Switch off' : 'Switch on' }}
                            </RowMenuItem>
                            <RowMenuItem danger @click="deleting = supplier">
                                <template #icon><Trash2 :size="16" /></template>
                                Delete
                            </RowMenuItem>
                        </RowMenu>
                    </div>
                </div>
            </div>
        </Card>

        <EmptyState
            v-else
            class="mt-4"
            icon="Users"
            title="No suppliers match that"
            message="Try a different word, or add a new supplier."
        >
            <template #action>
                <AppButton @click="openNew">Add supplier</AppButton>
            </template>
        </EmptyState>

        <ConfirmDialog
            :open="deleting !== null"
            :title="`Delete ${deleting?.name}?`"
            message="This only works if you have never ordered from them. Otherwise switch them off and they stop appearing on new orders."
            confirm="Delete"
            danger
            @confirm="remove"
            @close="deleting = null"
        />

        <BottomSheet
            :open="sheetOpen"
            :title="editing ? 'Edit supplier' : 'Add supplier'"
            @close="sheetOpen = false"
        >
            <div class="space-y-4">
                <TextField v-model="form.name" label="Supplier name" :error="form.errors.name" />
                <TextField v-model="form.contact_person" label="Who you speak to" :error="form.errors.contact_person" />
                <TextField v-model="form.phone" label="Phone number" inputmode="tel" :error="form.errors.phone" />
                <TextField v-model="form.address" label="Address" :error="form.errors.address" />
            </div>

            <template #footer>
                <AppButton block size="lg" :loading="form.processing" loading-text="Saving…" @click="save">
                    {{ editing ? 'Save supplier' : 'Add supplier' }}
                </AppButton>
            </template>
        </BottomSheet>
    </AdminLayout>
</template>
