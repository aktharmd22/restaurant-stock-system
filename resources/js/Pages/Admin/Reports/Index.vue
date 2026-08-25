<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, FileText } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    reports: { type: Array, required: true },
});
</script>

<template>
    <AdminLayout title="Reports">
        <Head title="Reports" />

        <p class="mb-4 max-w-2xl text-body text-ink-soft">
            Every report covers this month unless you change the dates, and every one can be saved
            as a spreadsheet or a PDF.
        </p>

        <div class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
            <Link
                v-for="report in reports"
                :key="report.key"
                :href="`/admin/reports/${report.key}`"
                class="flex min-h-touch items-center gap-4 p-card transition hover:bg-page"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary-light text-primary">
                    <FileText :size="20" aria-hidden="true" />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block text-body font-medium text-ink">{{ report.title }}</span>
                    <span class="block text-helper text-ink-soft">{{ report.hint }}</span>
                </span>

                <ChevronRight :size="20" class="shrink-0 text-ink-muted" aria-hidden="true" />
            </Link>
        </div>
    </AdminLayout>
</template>
