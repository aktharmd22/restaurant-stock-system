<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, PackageCheck, Plus } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import CountdownTimer from '@/Components/ui/CountdownTimer.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SpineCard from '@/Components/ui/SpineCard.vue';
import StatusPill from '@/Components/ui/StatusPill.vue';

const props = defineProps({
    greeting: { type: String, required: true },
    latest: { type: Object, default: null },
    cutoff: { type: Object, required: true },
    runningLow: { type: Array, default: () => [] },
    toReceive: { type: Number, default: 0 },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});

// One tap from "these are low" to a filled-in request.
const lowPrefillUrl = computed(
    () => `/b/ask?prefill=${props.runningLow.map((item) => item.id).join(',')}`,
);
</script>

<template>
    <BranchLayout>
        <Head title="Home" />

        <h1 class="text-title text-ink">{{ greeting }}, {{ user.first_name }}</h1>

        <div class="mt-4 space-y-4">
            <!-- Where today's request has got to -->
            <section>
                <h2 class="mb-2 text-heading text-ink">Your last request</h2>

                <SpineCard v-if="latest" :status="latest.status" as="a" interactive>
                    <Link :href="`/b/requests/${latest.id}`" class="block p-card">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-body font-medium text-ink">
                                    {{ latest.item_count }} items
                                </p>
                                <p class="mt-0.5 text-helper text-ink-soft">{{ latest.sent_at_text }}</p>
                            </div>
                            <StatusPill :status="latest.status" size="lg" />
                        </div>

                        <p class="mt-3 inline-flex items-center gap-1 text-body font-medium text-primary">
                            See what was approved
                            <ChevronRight :size="20" />
                        </p>
                    </Link>
                </SpineCard>

                <EmptyState
                    v-else
                    icon="ClipboardList"
                    title="No requests yet"
                    message="Tap the button at the bottom to ask the main store for stock."
                />
            </section>

            <!-- Anything waiting to be checked in -->
            <SpineCard v-if="toReceive > 0" status="sent" as="div">
                <Link href="/b/receive" class="flex items-center gap-3 p-card">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary-light text-primary">
                        <PackageCheck :size="20" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-body font-medium text-ink">
                            {{ toReceive }} delivery<span v-if="toReceive > 1">s</span> on the way
                        </span>
                        <span class="block text-helper text-ink-soft">Tap to confirm what arrived.</span>
                    </span>
                    <ChevronRight :size="20" class="shrink-0 text-ink-muted" />
                </Link>
            </SpineCard>

            <CountdownTimer :at="cutoff.at" :is-past="cutoff.is_past" :time="cutoff.time" />

            <!-- Running low -->
            <section v-if="runningLow.length">
                <div class="mb-2 flex items-end justify-between gap-3">
                    <h2 class="text-heading text-ink">Running low</h2>
                    <Link :href="lowPrefillUrl" class="text-body font-medium text-primary">
                        Add all
                    </Link>
                </div>

                <div class="space-y-2">
                    <SpineCard v-for="item in runningLow" :key="item.id" status="low">
                        <div class="flex items-center gap-3 p-card">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-body font-medium text-ink">{{ item.name }}</p>
                                <p class="text-helper text-partial">
                                    {{ item.on_hand_text }} left here
                                </p>
                            </div>

                            <Link
                                :href="`/b/ask?prefill=${item.id}`"
                                class="flex min-h-touch items-center gap-1.5 rounded-control border border-line px-4 text-body font-medium text-primary"
                            >
                                <Plus :size="20" />
                                Add
                            </Link>
                        </div>
                    </SpineCard>
                </div>
            </section>
        </div>

        <template #action>
            <AppButton href="/b/ask" size="lg" block>Ask for stock</AppButton>
        </template>
    </BranchLayout>
</template>
