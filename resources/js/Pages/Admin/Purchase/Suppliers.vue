<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Phone, Plus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import TextField from '@/Components/ui/TextField.vue';

defineProps({
    suppliers: { type: Array, required: true },
});

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

        <div class="space-y-2">
            <SpineCard
                v-for="supplier in suppliers"
                :key="supplier.id"
                :status="supplier.is_active ? 'approved' : 'cancelled'"
            >
                <div class="flex flex-wrap items-start gap-3 p-card">
                    <div class="min-w-0 flex-1">
                        <p class="text-body font-medium text-ink">{{ supplier.name }}</p>
                        <p class="text-helper text-ink-soft">
                            {{ supplier.contact_person ?? 'No contact name' }}
                            <span v-if="supplier.address"> · {{ supplier.address }}</span>
                        </p>
                        <a
                            v-if="supplier.phone"
                            :href="`tel:${supplier.phone}`"
                            class="mt-1 inline-flex min-h-touch items-center gap-2 text-body text-primary"
                        >
                            <Phone :size="18" />
                            {{ supplier.phone }}
                        </a>
                        <p class="text-helper text-ink-muted">{{ supplier.orders }} orders</p>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <AppButton variant="secondary" @click="openEdit(supplier)">Edit</AppButton>
                        <AppButton variant="ghost" @click="toggle(supplier)">
                            {{ supplier.is_active ? 'Switch off' : 'Switch on' }}
                        </AppButton>
                    </div>
                </div>
            </SpineCard>
        </div>

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
