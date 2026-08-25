<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, KeyRound, Plus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import Card from '@/Components/ui/Card.vue';

defineProps({
    people: { type: Array, required: true },
});

function toggle(person) {
    router.post(`/admin/settings/users/${person.id}/toggle`, {}, { preserveScroll: true });
}

function newPassword(person) {
    router.post(`/admin/settings/users/${person.id}/new-password`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="People">
        <Head title="People" />

        <template #header-action>
            <AppButton :href="route('admin.users.create')">
                <template #icon><Plus :size="20" /></template>
                Add person
            </AppButton>
        </template>

        <Link
            href="/admin/settings"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Settings
        </Link>

        <Card :padded="false">
            <div class="divide-y divide-line">
                <div
                    v-for="person in people"
                    :key="person.id"
                    class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5"
                    :class="person.is_active ? '' : 'opacity-60'"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-body font-medium text-ink">
                            {{ person.name }}
                            <span v-if="!person.is_active" class="text-helper text-ink-muted">
                                · switched off
                            </span>
                        </p>

                        <p class="truncate text-helper text-ink-soft">
                            {{ person.role_label }} · {{ person.branch ?? 'No branch' }} ·
                            <span class="tabular">{{ person.phone }}</span>
                            <span v-if="person.email"> · {{ person.email }}</span>
                        </p>

                        <p class="truncate text-helper text-ink-muted">
                            {{ person.last_login_at ? `Last signed in ${person.last_login_at}` : 'Never signed in' }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <AppButton variant="secondary" :href="`/admin/settings/users/${person.id}/edit`">
                            Edit
                        </AppButton>
                        <AppButton variant="ghost" @click="newPassword(person)">
                            <template #icon><KeyRound :size="16" /></template>
                            New password
                        </AppButton>
                        <AppButton variant="ghost" @click="toggle(person)">
                            {{ person.is_active ? 'Switch off' : 'Switch on' }}
                        </AppButton>
                    </div>
                </div>
            </div>
        </Card>

        <p class="mt-4 max-w-3xl text-helper text-ink-soft">
            A new password is shown here once, so you can read it out to them.
        </p>
    </AdminLayout>
</template>
