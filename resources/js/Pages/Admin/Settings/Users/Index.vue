<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, KeyRound, Plus, Power, Trash2 } from 'lucide-vue-next';
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
    people: { type: Array, required: true },
});

const search = ref('');
const role = ref('');
const branch = ref('');
const status = ref('all');
const deleting = ref(null);

/*
 * Filtered here rather than on the server: a restaurant has a dozen people,
 * and a round trip to hide four rows would be slower than the typing.
 */
const roles = computed(() => {
    const seen = new Map();
    props.people.forEach((person) => {
        if (person.role) seen.set(person.role, person.role_label);
    });

    return [...seen].map(([value, label]) => ({ value, label }));
});

const branches = computed(() => {
    const seen = new Set(props.people.map((person) => person.branch).filter(Boolean));

    return [...seen].sort().map((name) => ({ value: name, label: name }));
});

const shown = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.people.filter((person) => {
        const matchesTerm =
            !term ||
            person.name.toLowerCase().includes(term) ||
            (person.phone ?? '').includes(term) ||
            (person.email ?? '').toLowerCase().includes(term);

        const matchesRole = !role.value || person.role === role.value;
        const matchesBranch = !branch.value || person.branch === branch.value;
        const matchesStatus =
            status.value === 'all' ||
            (status.value === 'on' ? person.is_active : !person.is_active);

        return matchesTerm && matchesRole && matchesBranch && matchesStatus;
    });
});

const filtered = computed(
    () => search.value !== '' || role.value !== '' || branch.value !== '' || status.value !== 'all',
);

function clearFilters() {
    search.value = '';
    role.value = '';
    branch.value = '';
    status.value = 'all';
}

function toggle(person) {
    router.post(`/admin/settings/users/${person.id}/toggle`, {}, { preserveScroll: true });
}

function newPassword(person) {
    router.post(`/admin/settings/users/${person.id}/new-password`, {}, { preserveScroll: true });
}

function remove() {
    router.delete(`/admin/settings/users/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <AdminLayout title="People" :subtitle="`${people.length} can sign in`">
        <Head title="People" />

        <template #header-action>
            <AppButton :href="route('admin.users.create')">
                <template #icon><Plus :size="16" /></template>
                Add person
            </AppButton>
        </template>

        <Link
            href="/admin/settings"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="18" />
            Settings
        </Link>

        <Card>
            <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SearchField v-model="search" placeholder="Name, phone or email" />

                <SelectField
                    v-model="role"
                    label="What they do"
                    placeholder="Any job"
                    :options="roles"
                />

                <SelectField
                    v-model="branch"
                    label="Where they are"
                    placeholder="Everywhere"
                    :options="branches"
                />

                <SelectField
                    v-model="status"
                    label="Can sign in"
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
                    v-for="person in shown"
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
                            {{ person.role_label }} · {{ person.branch ?? 'No branch' }}
                        </p>

                        <!-- The phone number is how anyone is reached, and it is
                             also how they sign in. It never gets cut. -->
                        <p class="truncate text-helper text-ink-muted">
                            <span class="tabular">{{ person.phone }}</span>
                            <span v-if="person.email" class="hidden sm:inline"> · {{ person.email }}</span>
                            ·
                            {{ person.last_login_at ? `signed in ${person.last_login_at}` : 'never signed in' }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <AppButton variant="secondary" :href="`/admin/settings/users/${person.id}/edit`">
                            Edit
                        </AppButton>

                        <RowMenu :label="`More for ${person.name}`">
                            <RowMenuItem @click="newPassword(person)">
                                <template #icon><KeyRound :size="16" /></template>
                                New password
                            </RowMenuItem>
                            <RowMenuItem @click="toggle(person)">
                                <template #icon><Power :size="16" /></template>
                                {{ person.is_active ? 'Switch off' : 'Switch on' }}
                            </RowMenuItem>
                            <RowMenuItem danger @click="deleting = person">
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
            icon="Users"
            title="Nobody matches that"
            message="Try a different name, or clear what you picked."
        >
            <template #action>
                <AppButton variant="secondary" @click="clearFilters">Clear what I picked</AppButton>
            </template>
        </EmptyState>

        <p class="mt-4 max-w-3xl text-helper text-ink-soft">
            A new password is shown here once, so you can read it out to them. Switching someone off
            stops them signing in and keeps everything they have done.
        </p>

        <ConfirmDialog
            :open="deleting !== null"
            :title="`Delete ${deleting?.name}?`"
            message="This only works if they have never moved stock. If they have, switch them off instead — that already stops them signing in."
            confirm="Delete"
            danger
            @confirm="remove"
            @close="deleting = null"
        />
    </AdminLayout>
</template>
