<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    values: { type: Object, required: true },
});

const form = useForm({ ...props.values });
</script>

<template>
    <AdminLayout title="Restaurant name">
        <Head title="Restaurant name" />

        <Link
            href="/admin/settings"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Settings
        </Link>

        <form
            class="max-w-3xl space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg"
            @submit.prevent="form.put(route('admin.settings.business.update'))"
        >
            <TextField
                v-model="form.business_name"
                label="Restaurant name"
                hint="Shown on the sign-in screen, in the header and on every PDF."
                :error="form.errors.business_name"
            />

            <TextField
                v-model="form.business_tagline"
                label="One line under the name"
                :error="form.errors.business_tagline"
            />

            <TextField
                v-model="form.business_phone"
                label="Phone number"
                inputmode="tel"
                :error="form.errors.business_phone"
            />

            <TextField
                v-model="form.business_address"
                label="Address"
                :error="form.errors.business_address"
            />

            <TextField
                v-model="form.currency_symbol"
                label="Money symbol"
                hint="Used in front of every amount."
                :error="form.errors.currency_symbol"
            />

            <div class="flex justify-end pt-2">
                <AppButton type="submit" :loading="form.processing" loading-text="Saving…">
                    Save name
                </AppButton>
            </div>
        </form>
    </AdminLayout>
</template>
