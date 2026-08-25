<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Clock, Phone, Plus, Users } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import Card from '@/Components/ui/Card.vue';

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

        <Card :padded="false">
            <div class="divide-y divide-line">
                <div
                    v-for="branch in branches"
                    :key="branch.id"
                    class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5"
                    :class="branch.is_active ? '' : 'opacity-60'"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <p class="text-body font-medium text-ink">{{ branch.name }}</p>
                            <span class="text-helper tabular text-ink-muted">{{ branch.code }}</span>
                            <span v-if="branch.type === 'main'" class="text-helper text-primary">
                                Main store
                            </span>
                            <span v-if="!branch.is_active" class="text-helper text-ink-muted">
                                Switched off
                            </span>
                        </div>

                        <div class="mt-0.5 flex flex-wrap gap-x-4 gap-y-1 text-helper text-ink-soft">
                            <span class="inline-flex items-center gap-1.5">
                                <Clock :size="14" /> Cut-off {{ branch.cutoff_time }}
                            </span>
                            <span v-if="branch.phone" class="inline-flex items-center gap-1.5">
                                <Phone :size="14" /> {{ branch.phone }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <Users :size="14" /> {{ branch.people }} people
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <AppButton variant="secondary" :href="`/admin/settings/branches/${branch.id}/edit`">
                            Edit
                        </AppButton>
                        <AppButton v-if="branch.type !== 'main'" variant="ghost" @click="toggle(branch)">
                            {{ branch.is_active ? 'Switch off' : 'Switch on' }}
                        </AppButton>
                    </div>
                </div>
            </div>
        </Card>

        <p class="mt-4 max-w-3xl text-helper text-ink-soft">
            Branches are switched off, never deleted, so their stock history stays readable.
        </p>
    </AdminLayout>
</template>
