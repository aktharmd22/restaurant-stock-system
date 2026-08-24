<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Clock, Phone, Plus, Users } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';

defineProps({
    branches: { type: Array, required: true },
});

function toggle(branch) {
    router.post(`/admin/settings/branches/${branch.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Branches">
        <Head title="Branches" />

        <template #header-action>
            <AppButton :href="route('admin.branches.create')">
                <template #icon><Plus :size="20" /></template>
                Add branch
            </AppButton>
        </template>

        <Link
            href="/admin/settings"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="20" />
            Settings
        </Link>

        <div class="max-w-3xl space-y-2">
            <SpineCard
                v-for="branch in branches"
                :key="branch.id"
                :status="branch.is_active ? 'approved' : 'cancelled'"
            >
                <div class="flex flex-wrap items-start gap-3 p-card">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-body font-medium text-ink">{{ branch.name }}</p>
                            <span class="rounded-full bg-page px-2.5 py-1 text-helper tabular text-ink-soft">
                                {{ branch.code }}
                            </span>
                            <span
                                v-if="branch.type === 'main'"
                                class="rounded-full bg-primary-light px-2.5 py-1 text-helper text-primary"
                            >
                                Main store
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-helper text-ink-soft">
                            <span class="inline-flex items-center gap-1.5">
                                <Clock :size="16" /> Cut-off {{ branch.cutoff_time }}
                            </span>
                            <span v-if="branch.phone" class="inline-flex items-center gap-1.5">
                                <Phone :size="16" /> {{ branch.phone }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <Users :size="16" /> {{ branch.people }} people
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <AppButton variant="secondary" :href="`/admin/settings/branches/${branch.id}/edit`">
                            Edit
                        </AppButton>
                        <AppButton
                            v-if="branch.type !== 'main'"
                            variant="ghost"
                            @click="toggle(branch)"
                        >
                            {{ branch.is_active ? 'Switch off' : 'Switch on' }}
                        </AppButton>
                    </div>
                </div>
            </SpineCard>
        </div>

        <p class="mt-4 max-w-3xl text-helper text-ink-soft">
            Branches are switched off, never deleted, so their stock history stays readable.
        </p>
    </AdminLayout>
</template>
