<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Plus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import Card from '@/Components/ui/Card.vue';
import TextField from '@/Components/ui/TextField.vue';
import { COLOUR_OPTIONS, categoryColour } from '@/Support/categoryColours';

defineProps({
    categories: { type: Array, required: true },
});

const sheetOpen = ref(false);
const editing = ref(null);

const form = useForm({ name: '', colour: 'slate', sort_order: 0 });

const colourOptions = COLOUR_OPTIONS;

function openNew() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function openEdit(category) {
    editing.value = category;
    form.name = category.name;
    form.colour = category.colour ?? 'slate';
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

        <Card class="max-w-4xl" :padded="false">
            <div class="divide-y divide-line">
                <div
                    v-for="category in categories"
                    :key="category.id"
                    class="flex items-center gap-3 px-4 py-3 sm:px-5"
                    :class="category.is_active ? '' : 'opacity-60'"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control"
                        :class="categoryColour(category.colour).chip"
                        aria-hidden="true"
                    >
                        <span class="h-2.5 w-2.5 rounded-full" :class="categoryColour(category.colour).dot" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-body font-medium text-ink">{{ category.name }}</p>
                        <p class="text-helper text-ink-soft">
                            {{ category.items }} items
                            <span v-if="!category.is_active"> · hidden</span>
                        </p>
                    </div>

                    <AppButton variant="secondary" @click="openEdit(category)">Edit</AppButton>
                    <AppButton variant="ghost" @click="toggle(category)">
                        {{ category.is_active ? 'Hide' : 'Show' }}
                    </AppButton>
                </div>
            </div>
        </Card>

        <BottomSheet
            :open="sheetOpen"
            :title="editing ? 'Edit group' : 'Add group'"
            description="Groups are the chips a branch taps to filter the item list."
            @close="sheetOpen = false"
        >
            <div class="space-y-4">
                <TextField v-model="form.name" label="Group name" :error="form.errors.name" />
                <div>
                    <label class="mb-1.5 block text-helper text-ink-soft">Colour</label>
                    <p class="mb-2 text-helper text-ink-muted">
                        Branch staff find things by colour before they read the name.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in colourOptions"
                            :key="option.value"
                            type="button"
                            class="flex min-h-touch items-center gap-2 rounded-control px-3 text-body transition"
                            :class="[
                                categoryColour(option.value).chip,
                                form.colour === option.value
                                    ? 'font-medium ring-2 ring-inset ring-current'
                                    : 'opacity-70 hover:opacity-100',
                            ]"
                            :aria-pressed="form.colour === option.value"
                            @click="form.colour = option.value"
                        >
                            <span class="h-2.5 w-2.5 rounded-full" :class="option.swatch" aria-hidden="true" />
                            {{ option.label }}
                        </button>
                    </div>
                </div>

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
