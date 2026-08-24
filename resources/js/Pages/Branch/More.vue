<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
</script>

<template>
    <BranchLayout title="More">
        <Head title="More" />

        <div class="rounded-card border border-line bg-surface p-card">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                type="button"
                class="flex min-h-touch w-full items-center gap-3 rounded-control px-2 text-body text-ink"
            >
                <LogOut :size="20" aria-hidden="true" />
                Sign out
            </Link>
        </div>
    </BranchLayout>
</template>
