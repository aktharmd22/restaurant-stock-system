<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Package } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import SwitchField from '@/Components/ui/SwitchField.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    item: { type: Object, default: null },
    categories: { type: Array, required: true },
    branches: { type: Array, required: true },
    units: { type: Object, required: true },
});

const isNew = !props.item;
const photoPreview = ref(props.item?.photo ?? null);

const form = useForm({
    name: props.item?.name ?? '',
    category_id: props.item?.category_id ?? '',
    base_unit: props.item?.base_unit ?? 'g',
    order_unit: props.item?.order_unit ?? 'kg',
    conversion_factor: props.item?.conversion_factor ?? 1000,
    step: props.item?.step ?? 0.5,
    is_perishable: props.item?.is_perishable ?? false,
    shelf_life_days: props.item?.shelf_life_days ?? null,
    storage_location: props.item?.storage_location ?? '',
    is_active: props.item?.is_active ?? true,
    photo: null,
    par_levels: Object.fromEntries(
        props.branches.map((branch) => [branch.id, { par: branch.par, reorder: branch.reorder }]),
    ),
});

const unitOptions = (list) => list.map((unit) => ({ value: unit, label: unit }));

// Plain-English explanation of the conversion, so nobody has to guess.
const conversionHint = computed(
    () => `1 ${form.order_unit} = ${form.conversion_factor} ${form.base_unit}`,
);

function pickPhoto(event) {
    const file = event.target.files?.[0] ?? null;
    form.photo = file;
    photoPreview.value = file ? URL.createObjectURL(file) : props.item?.photo ?? null;
}

function submit() {
    isNew
        ? form.post(route('admin.items.store'))
        : form.post(route('admin.items.update', props.item.id));
}
</script>

<template>
    <AdminLayout :title="isNew ? 'Add item' : item.name">
        <Head :title="isNew ? 'Add item' : item.name" />

        <Link
            href="/admin/settings/items"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Items
        </Link>

        <form class="max-w-5xl space-y-4" @submit.prevent="submit">
            <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <h2 class="text-heading text-ink">What it is</h2>

                <div class="flex items-center gap-4">
                    <img
                        v-if="photoPreview"
                        :src="photoPreview"
                        alt=""
                        class="h-20 w-20 rounded-control border border-line object-cover"
                    />
                    <span
                        v-else
                        class="flex h-20 w-20 items-center justify-center rounded-control border border-line bg-page text-ink-muted"
                    >
                        <Package :size="24" aria-hidden="true" />
                    </span>

                    <div>
                        <label
                            class="inline-flex min-h-touch cursor-pointer items-center rounded-control border border-line bg-surface px-4 text-body text-ink hover:bg-page"
                        >
                            Choose a photo
                            <input type="file" accept="image/*" class="sr-only" @change="pickPhoto" />
                        </label>
                        <p class="mt-1.5 text-helper text-ink-soft">
                            A photo helps staff who read English slowly.
                        </p>
                        <p v-if="form.errors.photo" class="mt-1 text-helper text-rejected">
                            {{ form.errors.photo }}
                        </p>
                    </div>
                </div>

                <TextField v-model="form.name" label="Item name" :error="form.errors.name" />

                <SelectField
                    v-model="form.category_id"
                    label="Group"
                    placeholder="Pick a group"
                    :options="categories"
                    :error="form.errors.category_id"
                />

                <TextField
                    v-model="form.storage_location"
                    label="Where it is kept"
                    hint="Used to group the pack list so the store keeper walks the store once."
                    :error="form.errors.storage_location"
                />
            </section>

            <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <h2 class="text-heading text-ink">How it is measured</h2>
                <p class="text-helper text-ink-soft">
                    Stock is stored in the small unit so nothing is ever lost to rounding. People
                    always see the order unit.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectField
                        v-model="form.order_unit"
                        label="People order in"
                        :options="unitOptions(units.order)"
                        :error="form.errors.order_unit"
                    />

                    <SelectField
                        v-model="form.base_unit"
                        label="Stored in"
                        :options="unitOptions(units.base)"
                        :error="form.errors.base_unit"
                    />
                </div>

                <TextField
                    v-model="form.conversion_factor"
                    label="How many small units in one order unit"
                    inputmode="numeric"
                    :hint="conversionHint"
                    :error="form.errors.conversion_factor"
                />

                <TextField
                    v-model="form.step"
                    label="One tap changes the amount by"
                    inputmode="decimal"
                    :hint="`In ${form.order_unit}. Use a size people actually order in.`"
                    :error="form.errors.step"
                />

                <div class="border-t border-line pt-4">
                    <SwitchField
                        v-model="form.is_perishable"
                        label="This goes off"
                        hint="Perishable items get an expiry view and tighter par levels."
                    />
                </div>

                <TextField
                    v-if="form.is_perishable"
                    v-model="form.shelf_life_days"
                    label="Days it keeps"
                    inputmode="numeric"
                    :error="form.errors.shelf_life_days"
                />
            </section>

            <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <div>
                    <h2 class="text-heading text-ink">How much is a full shelf</h2>
                    <p class="mt-1 text-helper text-ink-soft">
                        Par level is what a full shelf looks like. The app suggests par level minus
                        what is left, so most of the time a branch just checks and sends. Running low
                        is anything under the second number. Both in {{ form.order_unit }}.
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="branch in branches"
                        :key="branch.id"
                        class="flex flex-wrap items-center gap-3 rounded-control border border-line p-3"
                    >
                        <p class="min-w-[140px] flex-1 text-body text-ink">
                            {{ branch.name }}
                            <span v-if="branch.is_main" class="text-helper text-ink-soft">· main store</span>
                        </p>

                        <label class="flex items-center gap-2">
                            <span class="text-helper text-ink-soft">Full shelf</span>
                            <input
                                v-model="form.par_levels[branch.id].par"
                                type="text"
                                inputmode="decimal"
                                class="min-h-touch w-24 rounded-control border border-line px-3 text-body tabular text-ink focus:border-primary focus:ring-0"
                            />
                        </label>

                        <label class="flex items-center gap-2">
                            <span class="text-helper text-ink-soft">Running low under</span>
                            <input
                                v-model="form.par_levels[branch.id].reorder"
                                type="text"
                                inputmode="decimal"
                                class="min-h-touch w-24 rounded-control border border-line px-3 text-body tabular text-ink focus:border-primary focus:ring-0"
                            />
                        </label>
                    </div>
                </div>
            </section>

            <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <SwitchField
                    v-model="form.is_active"
                    label="Branches can ask for this"
                    hint="Switch off to take it off the list without losing its history."
                />
            </section>

            <div class="flex justify-end gap-3">
                <AppButton variant="secondary" href="/admin/settings/items">Cancel</AppButton>
                <AppButton type="submit" :loading="form.processing" loading-text="Saving…">
                    {{ isNew ? 'Add item' : 'Save item' }}
                </AppButton>
            </div>
        </form>
    </AdminLayout>
</template>
