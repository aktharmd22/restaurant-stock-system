<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Plus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import TextField from '@/Components/ui/TextField.vue';

defineProps({
    categories: { type: Array, required: true },
});

const sheetOpen = ref(false);
const editing = ref(null);

const form = useForm({ name: '', sort_order: 0 });

function openNew() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEdit(category) {
    editing.value = category;
    form.name = category.name;
    form.sort_order = category.sort_order;
    form.clearErrors();
    sheetOpen.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            sheetOpen.value = false;
        },
    };

    editing.value
        ? form.put(route('admin.categories.update', editing.value.id), options)
        : form.post(route('admin.categories.store'), options);
}

function toggle(category) {
    router.post(`/admin/settings/categories/${category.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Item groups">
        <Head title="Item groups" />

        <template #header-action>
            <AppButton @click="openNew">
                <template #icon><Plus :size="20" /></template>
                Add group
            </AppButton>
        </template>

        <Link
            href="/admin/settings"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Settings
        </Link>

        <div class="max-w-4xl space-y-2">
            <SpineCard
                v-for="category in categories"
                :key="category.id"
                :status="category.is_active ? 'approved' : 'cancelled'"
            >
                <div class="flex items-center gap-3 p-card">
                    <div class="min-w-0 flex-1">
                        <p class="text-body font-medium text-ink">{{ category.name }}</p>
                        <p class="text-helper text-ink-soft">{{ category.items }} items</p>
                    </div>

                    <AppButton variant="secondary" @click="openEdit(category)">Edit</AppButton>
                    <AppButton variant="ghost" @click="toggle(category)">
                        {{ category.is_active ? 'Hide' : 'Show' }}
                    </AppButton>
                </div>
            </SpineCard>
        </div>

        <BottomSheet
            :open="sheetOpen"
            :title="editing ? 'Edit group' : 'Add group'"
            description="Groups are the chips a branch taps to filter the item list."
            @close="sheetOpen = false"
        >
            <div class="space-y-4">
                <TextField v-model="form.name" label="Group name" :error="form.errors.name" />
                <TextField
                    v-model="form.sort_order"
                    label="Order on screen"
                    inputmode="numeric"
                    hint="Smaller numbers come first."
                    :error="form.errors.sort_order"
                />
            </div>

            <template #footer>
                <AppButton block size="lg" :loading="form.processing" loading-text="Saving…" @click="save">
                    {{ editing ? 'Save group' : 'Add group' }}
                </AppButton>
            </template>
        </BottomSheet>
    </AdminLayout>
</template>
