<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { icons } from '@/Support/icons';

defineProps({
    reports: { type: Array, required: true },
});

/*
 * A different icon per report. Six rows wearing the same document glyph teach
 * the eye nothing; after a week someone should be able to find "Thrown away"
 * by its shape alone.
 */
const LOOKS = {
    stock_on_hand: { icon: 'Boxes', tone: 'bg-tile-green text-approved' },
    request_variance: { icon: 'ClipboardList', tone: 'bg-tile-blue text-primary' },
    consumption: { icon: 'TrendingDown', tone: 'bg-tile-violet text-tile-violet-ink' },
    wastage: { icon: 'Trash2', tone: 'bg-tile-rose text-rejected' },
    cost_per_branch: { icon: 'Scale', tone: 'bg-tile-amber text-partial' },
    price_trend: { icon: 'TrendingUp', tone: 'bg-tile-cyan text-tile-cyan-ink' },
};

const look = (key) => LOOKS[key] ?? { icon: 'FileText', tone: 'bg-primary-light text-primary' };
</script>

<template>
    <AdminLayout
        title="Reports"
        subtitle="This month unless you change the dates. Every one saves as a spreadsheet or a PDF."
    >
        <Head title="Reports" />

        <!-- Destinations, not a queue: tiles read faster than a list of rows
             when nothing here is more urgent than anything else. -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="report in reports"
                :key="report.key"
                :href="`/admin/reports/${report.key}`"
                class="group flex flex-col rounded-card border border-line bg-surface p-card shadow-card transition hover:border-primary/40 hover:shadow-float"
            >
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-control"
                    :class="look(report.key).tone"
                >
                    <component :is="icons[look(report.key).icon]" :size="20" aria-hidden="true" />
                </span>

                <span class="mt-3 block text-heading text-ink">{{ report.title }}</span>
                <span class="mt-1 block flex-1 text-helper text-ink-soft">{{ report.hint }}</span>

                <span class="mt-3 inline-flex items-center gap-1.5 text-body font-medium text-primary">
                    Open
                    <ArrowRight
                        :size="16"
                        class="transition group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </span>
            </Link>
        </div>
    </AdminLayout>
</template>
