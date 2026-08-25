<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ChevronLeft,
    Download,
    Eye,
    EyeOff,
    FileSpreadsheet,
    Package,
    Plus,
    Search,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import Card from '@/Components/ui/Card.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import RowMenu from '@/Components/ui/RowMenu.vue';
import RowMenuItem from '@/Components/ui/RowMenuItem.vue';
import SelectField from '@/Components/ui/SelectField.vue';

const props = defineProps({
    items: { type: Object, required: true },
    categories: { type: Array, required: true },
    units: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
});

const page = usePage();

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? '');
const unit = ref(props.filters.unit ?? '');
const status = ref(props.filters.status ?? 'all');

const importOpen = ref(false);
const deleting = ref(null);

let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => reload({ search: value || undefined }), 300);
});

watch([category, unit, status], () => reload());

const activeFilters = computed(
    () => [category.value, unit.value, status.value !== 'all' ? status.value : ''].filter(Boolean).length,
);

function reload(extra = {}) {
    router.get(
        '/admin/settings/items',
        {
            search: search.value || undefined,
            category: category.value || undefined,
            unit: unit.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            ...extra,
        },
        { preserveState: true, preserveScroll: true, replace: true, only: ['items', 'filters'] },
    );
}

function clearFilters() {
    search.value = '';
    category.value = '';
    unit.value = '';
    status.value = 'all';
}

function toggle(item) {
    router.post(`/admin/settings/items/${item.id}/toggle`, {}, { preserveScroll: true });
}

function remove() {
    router.delete(`/admin/settings/items/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

/*
 * The import. A restaurant with sixty items is not typing them in one at a
 * time, and the sheet they already keep is the fastest way in. The template
 * carries their own group names so the first attempt works.
 */
const importForm = useForm({ file: null, update_existing: true });

const result = computed(() => page.props.flash?.import ?? null);

function pickFile(event) {
    importForm.file = event.target.files?.[0] ?? null;
}

function runImport() {
    importForm.post('/admin/settings/items/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            importOpen.value = false;
        },
    });
}
</script>

<template>
    <AdminLayout title="Items" :subtitle="`${items.total} on the list`">
        <Head title="Items" />

        <template #header-action>
            <div class="flex gap-2">
                <AppButton variant="secondary" @click="importOpen = true">
                    <template #icon><FileSpreadsheet :size="16" /></template>
                    Import
                </AppButton>
                <AppButton :href="route('admin.items.create')">
                    <template #icon><Plus :size="16" /></template>
                    Add item
                </AppButton>
            </div>
        </template>

        <Link
            href="/admin/settings"
            class="-ml-3 mb-4 inline-flex min-h-touch items-center gap-2 px-3 text-body text-ink-soft hover:text-ink"
        >
            <ChevronLeft :size="18" />
            Settings
        </Link>

        <!-- Find things. Dropdowns rather than a row of chips: three questions
             about one list belong side by side, not stacked as three rows. -->
        <Card>
            <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="item-search" class="mb-1.5 block text-helper text-ink-soft">
                        Search
                    </label>
                    <div class="relative">
                        <Search
                            :size="18"
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted"
                        />
                        <input
                            id="item-search"
                            v-model="search"
                            type="search"
                            inputmode="search"
                            placeholder="Name or where it is kept"
                            class="min-h-control w-full rounded-control border border-line bg-surface pl-10 pr-3 text-body text-ink focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                        />
                    </div>
                </div>

                <SelectField
                    v-model="category"
                    label="Group"
                    placeholder="Every group"
                    :options="categories"
                />

                <SelectField
                    v-model="unit"
                    label="Ordered by"
                    placeholder="Any unit"
                    :options="units.map((u) => ({ value: u, label: u }))"
                />

                <SelectField
                    v-model="status"
                    label="Shown to branches"
                    :options="[
                        { value: 'all', label: 'Shown and hidden' },
                        { value: 'shown', label: 'Shown only' },
                        { value: 'hidden', label: 'Hidden only' },
                    ]"
                />
            </div>

            <button
                v-if="activeFilters || search"
                type="button"
                class="mt-2 inline-flex min-h-touch items-center gap-1.5 text-body text-primary"
                @click="clearFilters"
            >
                <X :size="14" />
                Clear what I picked
            </button>
        </Card>

        <!-- What the last import did. Stays until the next page load. -->
        <Card
            v-if="result"
            class="mt-4"
            :title="`Brought in: ${result.added} added, ${result.updated} updated`"
            :hint="
                result.problems.length
                    ? `${result.problems.length} row${result.problems.length === 1 ? '' : 's'} could not be read`
                    : 'Every row went in'
            "
            :padded="false"
        >
            <div v-if="result.problems.length" class="divide-y divide-line">
                <div
                    v-for="problem in result.problems"
                    :key="`${problem.row}-${problem.name}`"
                    class="flex flex-wrap items-baseline gap-x-3 px-4 py-2.5 sm:px-5"
                >
                    <span class="text-helper tabular text-ink-muted">Row {{ problem.row }}</span>
                    <span class="text-body font-medium text-ink">{{ problem.name || '(no name)' }}</span>
                    <span class="text-helper text-partial">{{ problem.problem }}</span>
                </div>
            </div>
        </Card>

        <div v-if="items.data.length" class="mt-4">
            <Card :padded="false">
                <div class="divide-y divide-line">
                    <div
                        v-for="item in items.data"
                        :key="item.id"
                        class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5"
                        :class="item.is_active ? '' : 'opacity-60'"
                    >
                        <img
                            v-if="item.photo"
                            :src="item.photo"
                            :alt="item.name"
                            loading="lazy"
                            class="h-10 w-10 shrink-0 rounded-control border border-line object-cover"
                        />
                        <span
                            v-else
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-page text-ink-muted"
                        >
                            <Package :size="18" aria-hidden="true" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-body font-medium text-ink">
                                {{ item.name }}
                                <span v-if="!item.is_active" class="text-helper text-ink-muted">
                                    · hidden
                                </span>
                            </p>
                            <p class="truncate text-helper text-ink-soft">
                                {{ item.category }} · sold by {{ item.unit }}
                                <span class="hidden text-ink-muted sm:inline">
                                    (1 {{ item.unit }} = {{ item.conversion }} {{ item.base_unit }})
                                </span>
                                <span v-if="item.storage_location" class="text-ink-muted">
                                    · {{ item.storage_location }}
                                </span>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <AppButton variant="secondary" :href="`/admin/settings/items/${item.id}/edit`">
                                Edit
                            </AppButton>

                            <RowMenu :label="`More for ${item.name}`">
                                <RowMenuItem @click="toggle(item)">
                                    <template #icon>
                                        <component :is="item.is_active ? EyeOff : Eye" :size="16" />
                                    </template>
                                    {{ item.is_active ? 'Hide from branches' : 'Show to branches' }}
                                </RowMenuItem>
                                <RowMenuItem danger @click="deleting = item">
                                    <template #icon><Trash2 :size="16" /></template>
                                    Delete
                                </RowMenuItem>
                            </RowMenu>
                        </div>
                    </div>
                </div>
            </Card>

            <Pagination :links="items.links" :meta="items" />
        </div>

        <EmptyState
            v-else
            class="mt-4"
            icon="Package"
            title="No items found"
            :message="
                activeFilters || search
                    ? 'Nothing matches what you picked. Try clearing a filter.'
                    : 'Add your first item, or bring the whole list in from a spreadsheet.'
            "
        >
            <template #action>
                <div class="flex flex-wrap justify-center gap-2">
                    <AppButton :href="route('admin.items.create')">Add item</AppButton>
                    <AppButton variant="secondary" @click="importOpen = true">
                        Import from Excel
                    </AppButton>
                </div>
            </template>
        </EmptyState>

        <!-- Import: get the sheet, fill it in, send it back. -->
        <BottomSheet
            :open="importOpen"
            title="Bring items in from Excel"
            description="Start from the template. It already has your own group names in it."
            @close="importOpen = false"
        >
            <ol class="space-y-4">
                <li class="flex gap-3">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-light text-micro font-semibold text-primary"
                    >
                        1
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-body text-ink">Get the blank sheet</p>
                        <a
                            href="/admin/settings/items/template"
                            class="mt-2 inline-flex min-h-touch items-center gap-2 rounded-control border border-line px-4 text-body font-medium text-primary transition hover:border-primary"
                        >
                            <Download :size="16" />
                            Download template
                        </a>
                    </div>
                </li>

                <li class="flex gap-3">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-light text-micro font-semibold text-primary"
                    >
                        2
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-body text-ink">Fill in one row per item</p>
                        <p class="mt-0.5 text-helper text-ink-soft">
                            Delete the two grey example rows. The second sheet lists the groups and
                            units you can write.
                        </p>
                    </div>
                </li>

                <li class="flex gap-3">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-light text-micro font-semibold text-primary"
                    >
                        3
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-body text-ink">Send it back</p>

                        <label
                            class="mt-2 flex min-h-touch cursor-pointer items-center gap-2 rounded-control border border-dashed border-line px-4 text-body text-ink transition hover:border-primary"
                        >
                            <Upload :size="16" />
                            {{ importForm.file ? importForm.file.name : 'Choose the filled-in sheet' }}
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                class="sr-only"
                                @change="pickFile"
                            />
                        </label>

                        <p v-if="importForm.errors.file" class="mt-2 text-helper text-rejected">
                            {{ importForm.errors.file }}
                        </p>

                        <label class="mt-3 flex min-h-touch cursor-pointer items-center gap-2.5 text-body text-ink">
                            <input
                                v-model="importForm.update_existing"
                                type="checkbox"
                                class="h-5 w-5 rounded border-line text-primary focus:ring-primary"
                            />
                            Update items that are already on the list
                        </label>
                    </div>
                </li>
            </ol>

            <template #footer>
                <AppButton
                    block
                    size="lg"
                    :disabled="!importForm.file"
                    :loading="importForm.processing"
                    loading-text="Reading the sheet…"
                    @click="runImport"
                >
                    Bring them in
                </AppButton>
            </template>
        </BottomSheet>

        <ConfirmDialog
            :open="deleting !== null"
            :title="`Delete ${deleting?.name}?`"
            message="This only works while an item has never held stock. If it has, hide it instead and it stops showing up for branches."
            confirm="Delete"
            danger
            @confirm="remove"
            @close="deleting = null"
        />
    </AdminLayout>
</template>
