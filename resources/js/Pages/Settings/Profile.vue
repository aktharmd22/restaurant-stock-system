<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import TextField from '@/Components/ui/TextField.vue';

const props = defineProps({
    person: { type: Object, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const Layout = computed(() => (user.value.is_admin_side ? AdminLayout : BranchLayout));

const details = useForm({
    name: props.person.name,
    email: props.person.email ?? '',
    phone: props.person.phone ?? '',
});

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function savePassword() {
    password.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => password.reset(),
    });
}
</script>

<template>
    <component :is="Layout" title="Your details" :back="user.is_admin_side ? undefined : '/b/more'">
        <Head title="Your details" />

        <div class="max-w-3xl space-y-4">
            <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <p class="text-body text-ink">{{ person.branch ?? 'No branch' }}</p>
                <p class="text-helper text-ink-soft">
                    Your admin decides what you are allowed to do. Ask them if something is missing.
                </p>
            </section>

            <form
                class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg"
                @submit.prevent="details.put(route('profile.update'), { preserveScroll: true })"
            >
                <h2 class="text-heading text-ink">Your details</h2>

                <TextField v-model="details.name" label="Name" :error="details.errors.name" />
                <TextField
                    v-model="details.phone"
                    label="Phone number"
                    inputmode="tel"
                    hint="You sign in with this."
                    :error="details.errors.phone"
                />
                <TextField
                    v-model="details.email"
                    label="Email (optional)"
                    type="email"
                    inputmode="email"
                    :error="details.errors.email"
                />

                <div class="flex justify-end">
                    <AppButton type="submit" :loading="details.processing" loading-text="Saving…">
                        Save details
                    </AppButton>
                </div>
            </form>

            <form
                class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg"
                @submit.prevent="savePassword"
            >
                <h2 class="text-heading text-ink">Change your password</h2>

                <TextField
                    v-model="password.current_password"
                    label="Password you use now"
                    type="password"
                    autocomplete="current-password"
                    :error="password.errors.current_password"
                />
                <TextField
                    v-model="password.password"
                    label="New password"
                    type="password"
                    autocomplete="new-password"
                    hint="At least 8 characters."
                    :error="password.errors.password"
                />
                <TextField
                    v-model="password.password_confirmation"
                    label="New password again"
                    type="password"
                    autocomplete="new-password"
                    :error="password.errors.password_confirmation"
                />

                <div class="flex justify-end">
                    <AppButton type="submit" :loading="password.processing" loading-text="Saving…">
                        Change password
                    </AppButton>
                </div>
            </form>
        </div>
    </component>
</template>
