<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import SwitchField from '@/Components/ui/SwitchField.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    branch: { type: Object, default: null },
});

const isNew = !props.branch;

const form = useForm({
    name: props.branch?.name ?? '',
    code: props.branch?.code ?? '',
    type: props.branch?.type ?? 'sub',
    phone: props.branch?.phone ?? '',
    address: props.branch?.address ?? '',
    cutoff_time: props.branch?.cutoff_time ?? '18:00',
    is_active: props.branch?.is_active ?? true,
});

function submit() {
    isNew
        ? form.post(route('admin.branches.store'))
        : form.put(route('admin.branches.update', props.branch.id));
}
</script>

<template>
    <AdminLayout :title="isNew ? 'Add branch' : branch.name">
        <Head :title="isNew ? 'Add branch' : branch.name" />

        <Link
            href="/admin/settings/branches"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Branches
        </Link>

        <form class="max-w-xl space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg" @submit.prevent="submit">
            <TextField v-model="form.name" label="Branch name" :error="form.errors.name" />

            <TextField
                v-model="form.code"
                label="Short code"
                hint="Used on request numbers, like PARK."
                :error="form.errors.code"
            />

            <SelectField
                v-model="form.type"
                label="Kind of branch"
                :options="[
                    { value: 'sub', label: 'Branch - asks the main store for stock' },
                    { value: 'main', label: 'Main store - holds stock and approves' },
                ]"
                :error="form.errors.type"
            />

            <TextField
                v-model="form.cutoff_time"
                label="Daily cut-off time"
                type="time"
                hint="After this, a request is marked Late. It is never blocked - a branch can ask at any hour."
                :error="form.errors.cutoff_time"
            />

            <TextField v-model="form.phone" label="Phone number" inputmode="tel" :error="form.errors.phone" />
            <TextField v-model="form.address" label="Address" :error="form.errors.address" />

            <div class="border-t border-line pt-4">
                <SwitchField
                    v-model="form.is_active"
                    label="This branch is open"
                    hint="Switch off to stop it sending requests."
                />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <AppButton variant="secondary" href="/admin/settings/branches">Cancel</AppButton>
                <AppButton type="submit" :loading="form.processing" loading-text="Saving…">
                    {{ isNew ? 'Add branch' : 'Save branch' }}
                </AppButton>
            </div>
        </form>
    </AdminLayout>
</template>
