<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    phone: { type: String, required: true },
    devCode: { type: String, default: null },
});

const form = useForm({
    code: '',
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <GuestLayout>
        <Head title="Enter your code" />

        <Link
            :href="route('password.request')"
            class="-ml-3 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ArrowLeft :size="20" />
            Use a different number
        </Link>

        <h1 class="mt-4 text-title text-ink">Enter your code</h1>
        <p class="mt-2 text-body text-ink-soft">
            We sent a 6-digit code to {{ props.phone }}. It works for 10 minutes.
        </p>

        <!-- Local development only: there is no SMS provider yet, so the code
             would otherwise be unreachable. -->
        <p
            v-if="props.devCode"
            class="mt-4 rounded-control border border-waiting/20 bg-waiting-bg px-4 py-3 text-body text-waiting"
        >
            Test mode: your code is <span class="font-bold tabular">{{ props.devCode }}</span>
        </p>

        <form class="mt-8 space-y-4" @submit.prevent="form.post(route('password.reset'))">
            <TextField
                v-model="form.code"
                label="6-digit code"
                inputmode="numeric"
                autocomplete="one-time-code"
                :error="form.errors.code"
                autofocus
            />

            <TextField
                v-model="form.password"
                label="New password"
                type="password"
                autocomplete="new-password"
                hint="At least 8 characters."
                :error="form.errors.password"
            />

            <TextField
                v-model="form.password_confirmation"
                label="New password again"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <AppButton
                type="submit"
                size="lg"
                block
                :loading="form.processing"
                loading-text="Saving…"
            >
                Save new password
            </AppButton>
        </form>
    </GuestLayout>
</template>
