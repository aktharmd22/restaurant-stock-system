<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import SelectField from '@/Components/ui/SelectField.vue';
import SwitchField from '@/Components/ui/SwitchField.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    person: { type: Object, default: null },
    branches: { type: Array, required: true },
    roles: { type: Array, required: true },
});

const isNew = !props.person;

const form = useForm({
    name: props.person?.name ?? '',
    phone: props.person?.phone ?? '',
    email: props.person?.email ?? '',
    branch_id: props.person?.branch_id ?? '',
    role: props.person?.role ?? 'branch_manager',
    password: '',
    is_active: props.person?.is_active ?? true,
});

function submit() {
    isNew
        ? form.post(route('admin.users.store'))
        : form.put(route('admin.users.update', props.person.id));
}
</script>

<template>
    <AdminLayout :title="isNew ? 'Add person' : person.name">
        <Head :title="isNew ? 'Add person' : person.name" />

        <Link
            href="/admin/settings/users"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            People
        </Link>

        <form class="max-w-xl space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg" @submit.prevent="submit">
            <TextField v-model="form.name" label="Name" :error="form.errors.name" />

            <!-- Either of these is a sign-in. Kitchen staff have a phone and
                 no email; office people are often the other way round. -->
            <fieldset class="space-y-4 rounded-control bg-page p-4">
                <!-- w-full keeps the legend on its own line instead of cutting
                     a notch out of the top of the box. -->
                <legend class="w-full">
                    <span class="block text-body font-medium text-ink">How they sign in</span>
                    <span class="mt-0.5 block text-helper text-ink-soft">
                        A phone number or an email — either one works. Fill in both if you have both.
                    </span>
                </legend>

                <TextField
                    v-model="form.phone"
                    label="Phone number"
                    inputmode="tel"
                    :error="form.errors.phone"
                />

                <TextField
                    v-model="form.email"
                    label="Email"
                    type="email"
                    inputmode="email"
                    :error="form.errors.email"
                />
            </fieldset>

            <SelectField
                v-model="form.branch_id"
                label="Works at"
                placeholder="Pick a branch"
                :options="branches"
                :error="form.errors.branch_id"
            />

            <SelectField
                v-model="form.role"
                label="Allowed to"
                :options="roles"
                :error="form.errors.role"
            />

            <TextField
                v-model="form.password"
                :label="isNew ? 'First password' : 'New password (leave blank to keep the old one)'"
                type="password"
                autocomplete="new-password"
                hint="At least 8 characters. Tell them what it is."
                :error="form.errors.password"
            />

            <div class="border-t border-line pt-4">
                <SwitchField
                    v-model="form.is_active"
                    label="This person can sign in"
                    hint="Switch off when someone leaves. Their history stays."
                />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <AppButton variant="secondary" href="/admin/settings/users">Cancel</AppButton>
                <AppButton type="submit" :loading="form.processing" loading-text="Saving…">
                    {{ isNew ? 'Add person' : 'Save person' }}
                </AppButton>
            </div>
        </form>
    </AdminLayout>
</template>
