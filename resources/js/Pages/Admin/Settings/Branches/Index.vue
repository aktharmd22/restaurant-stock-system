<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Clock, Phone, Plus, Power, Trash2, Users } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import Card from '@/Components/ui/Card.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import RowMenu from '@/Components/ui/RowMenu.vue';
import RowMenuItem from '@/Components/ui/RowMenuItem.vue';
import SearchField from '@/Components/ui/SearchField.vue';
import SelectField from '@/Components/ui/SelectField.vue';

const props = defineProps({
    branches: { type: Array, required: true },
});

const search = ref('');
const status = ref('all');
const deleting = ref(null);

const shown = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.branches.filter((branch) => {
        const matchesTerm =
            !term ||
            branch.name.toLowerCase().includes(term) ||
            branch.code.toLowerCase().includes(term) ||
            (branch.phone ?? '').includes(term);

        const matchesStatus =
            status.value === 'all' || (status.value === 'on' ? branch.is_active : !branch.is_active);

        return matchesTerm && matchesStatus;
    });
});

function toggle(branch) {
    router.post(`/admin/settings/branches/${branch.id}/toggle`, {}, { preserveScroll: true });
}

function remove() {
    router.delete(`/admin/settings/branches/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <AdminLayout title="Branches" :subtitle="`${branches.length} places`">
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

        <Card>
            <div class="grid items-end gap-3 sm:grid-cols-2">
                <SearchField v-model="search" placeholder="Name, code or phone" />
                <SelectField
                    v-model="status"
                    label="Taking requests"
                    :options="[
                        { value: 'all', label: 'On and off' },
                        { value: 'on', label: 'On only' },
                        { value: 'off', label: 'Switched off only' },
                    ]"
                />
            </div>
        </Card>

        <Card v-if="shown.length" class="mt-4" :padded="false">
            <div class="divide-y divide-line">
                <div
                    v-for="branch in shown"
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

                    <div class="flex shrink-0 items-center gap-1">
                        <AppButton variant="secondary" :href="`/admin/settings/branches/${branch.id}/edit`">
                            Edit
                        </AppButton>

                        <RowMenu v-if="branch.type !== 'main'" :label="`More for ${branch.name}`">
                            <RowMenuItem @click="toggle(branch)">
                                <template #icon><Power :size="16" /></template>
                                {{ branch.is_active ? 'Switch off' : 'Switch on' }}
                            </RowMenuItem>
                            <RowMenuItem danger @click="deleting = branch">
                                <template #icon><Trash2 :size="16" /></template>
                                Delete
                            </RowMenuItem>
                        </RowMenu>
                    </div>
                </div>
            </div>
        </Card>

        <EmptyState
            v-else
            class="mt-4"
            icon="Store"
            title="No branches match that"
            message="Try a different name, or clear what you picked."
        />

        <p class="mt-4 max-w-3xl text-helper text-ink-soft">
            A branch that has ever held stock is switched off rather than deleted, so its history
            stays readable. Deleting only works on one added by mistake.
        </p>

        <ConfirmDialog
            :open="deleting !== null"
            :title="`Delete ${deleting?.name}?`"
            message="This only works if the branch has never held stock and nobody is posted there. Otherwise switch it off."
            confirm="Delete"
            danger
            @confirm="remove"
            @close="deleting = null"
        />
    </AdminLayout>
</template>
