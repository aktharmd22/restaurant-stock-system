<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import TextField from '@/Components/ui/TextField.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    login: '',
    password: '',
    // These people should not have to sign in every day.
    remember: true,
});

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Sign in" />

        <h1 class="text-title text-ink">Sign in</h1>
        <p class="mt-2 text-body text-ink-soft">Use your phone number or email.</p>

        <div
            v-if="status"
            class="mt-6 rounded-control border border-approved/20 bg-approved-bg px-4 py-3 text-body text-approved"
        >
            {{ status }}
        </div>

        <form class="mt-8 space-y-4" @submit.prevent="submit">
            <TextField
                v-model="form.login"
                label="Phone number or email"
                autocomplete="username"
                inputmode="text"
                :error="form.errors.login"
            />

            <TextField
                v-model="form.password"
                label="Password"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <label class="flex min-h-touch cursor-pointer items-center gap-3 text-body text-ink">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-5 w-5 rounded border-line text-primary focus:ring-primary"
                />
                Keep me signed in
            </label>

            <AppButton
                type="submit"
                size="lg"
                block
                :loading="form.processing"
                loading-text="Signing in…"
            >
                Sign in
            </AppButton>
        </form>

        <div class="mt-6 text-center">
            <Link
                :href="route('password.request')"
                class="inline-flex min-h-touch items-center px-3 text-body font-medium text-primary hover:underline"
            >
                Forgot your password?
            </Link>
        </div>

        <p class="mt-8 text-center text-helper text-ink-soft">
            Your admin creates accounts. Ask them if you cannot get in.
        </p>
    </GuestLayout>
</template>
