<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import TextField from '@/Components/ui/TextField.vue';

const form = useForm({ phone: '' });
</script>

<template>
    <GuestLayout>
        <Head title="Forgot password" />

        <Link
            :href="route('login')"
            class="-ml-3 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ArrowLeft :size="20" />
            Back to sign in
        </Link>

        <h1 class="mt-4 text-title text-ink">Forgot your password?</h1>
        <p class="mt-2 text-body text-ink-soft">
            Give us your phone number. We will send you a code to set a new password.
        </p>

        <form class="mt-8 space-y-4" @submit.prevent="form.post(route('password.send'))">
            <TextField
                v-model="form.phone"
                label="Phone number"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                :error="form.errors.phone"
            />

            <AppButton
                type="submit"
                size="lg"
                block
                :loading="form.processing"
                loading-text="Sending…"
            >
                Send me a code
            </AppButton>
        </form>
    </GuestLayout>
</template>
