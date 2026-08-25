<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, PackageCheck, Plus } from 'lucide-vue-next';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import Card from '@/Components/ui/Card.vue';
import CountdownTimer from '@/Components/ui/CountdownTimer.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import InstallPrompt from '@/Components/ui/InstallPrompt.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import StatusText from '@/Components/ui/StatusText.vue';

const props = defineProps({
    greeting: { type: String, required: true },
    stats: { type: Object, default: () => ({}) },
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

const quickLinks = [
    { href: '/b/ask', label: 'Ask for stock' },
    { href: '/b/receive', label: 'Receive' },
    { href: '/b/stock', label: 'Stock left' },
    { href: '/waste', label: 'Thrown away' },
];
</script>

<template>
    <BranchLayout>
        <Head title="Home" />

        <!-- The one primary action. On a laptop the layout puts it in the header. -->
        <template #action>
            <AppButton href="/b/ask" size="lg" class="w-full lg:w-auto">Ask for stock</AppButton>
        </template>

        <h1 class="text-title text-ink lg:hidden">{{ greeting }}, {{ user.first_name }}</h1>
        <p class="hidden text-body text-ink-soft lg:block">
            {{ greeting }}, {{ user.first_name }}. Here is where everything stands.
        </p>

        <!-- Laptop: the whole picture at a glance -->
        <div class="mt-4 hidden grid-cols-2 gap-3 lg:grid xl:grid-cols-4 xl:gap-4">
            <StatCard
                label="Waiting for approval"
                :value="stats.waiting ?? 0"
                icon="Clock"
                tone="amber"
                href="/b/requests"
                hint="The main store has not looked yet"
            />
            <StatCard
                label="On the way"
                :value="stats.on_the_way ?? 0"
                icon="Truck"
                tone="blue"
                href="/b/receive"
                hint="Confirm it when it arrives"
            />
            <StatCard
                label="Running low"
                :value="stats.running_low ?? 0"
                icon="TrendingDown"
                tone="rose"
                :href="lowPrefillUrl"
                hint="Below the level you set"
            />
            <StatCard
                label="Items on your shelf"
                :value="stats.on_shelf ?? 0"
                icon="Boxes"
                tone="green"
                href="/b/stock"
                hint="Anything with stock left"
            />
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <!-- Left: what is happening with your requests -->
            <div class="space-y-4 lg:col-span-2">
                <section>
                    <h2 class="mb-2 text-heading text-ink">Your last request</h2>

                    <Link
                        v-if="latest"
                        :href="`/b/requests/${latest.id}`"
                        class="block rounded-card border border-line bg-surface p-card shadow-card transition hover:border-ink-muted"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-title text-ink">{{ latest.item_count }} items</p>
                                <p class="mt-0.5 text-helper text-ink-soft">{{ latest.sent_at_text }}</p>
                            </div>
                            <StatusText :status="latest.status" />
                        </div>

                        <p class="mt-3 inline-flex items-center gap-1 text-body font-medium text-primary">
                            See what was approved
                            <ChevronRight :size="16" />
                        </p>
                    </Link>

                    <EmptyState
                        v-else
                        icon="ClipboardList"
                        title="No requests yet"
                        message="Ask the main store for stock and it will show up here."
                    >
                        <template #action>
                            <AppButton href="/b/ask">Ask for stock</AppButton>
                        </template>
                    </EmptyState>
                </section>

                <div v-if="toReceive > 0" class="rounded-card border border-line bg-surface shadow-card">
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
                </div>

                <Card
                    v-if="runningLow.length"
                    title="Running low"
                    hint="Below the level you set as low"
                    :padded="false"
                >
                    <template #action>
                        <Link
                            :href="lowPrefillUrl"
                            class="flex min-h-touch items-center gap-1 px-1 text-body font-medium text-primary"
                        >
                            Add all
                            <Plus :size="16" />
                        </Link>
                    </template>

                    <ul class="divide-y divide-line">
                        <li
                            v-for="item in runningLow"
                            :key="item.id"
                            class="flex items-center gap-3 px-card py-2.5"
                        >
                            <span class="h-2 w-2 shrink-0 rounded-full bg-partial" aria-hidden="true" />

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-body font-medium text-ink">{{ item.name }}</span>
                                <span class="block text-helper text-partial">
                                    {{ item.on_hand_text }} left here
                                </span>
                            </span>

                            <span class="hidden text-helper text-ink-soft sm:block">
                                suggest {{ item.suggested }} {{ item.unit }}
                            </span>

                            <Link
                                :href="`/b/ask?prefill=${item.id}`"
                                class="flex min-h-touch shrink-0 items-center gap-1.5 rounded-control border border-line px-3 text-body font-medium text-primary transition hover:border-primary"
                            >
                                <Plus :size="16" />
                                Add
                            </Link>
                        </li>
                    </ul>
                </Card>
            </div>

            <!-- Right: the clock, shortcuts, and getting this onto a phone -->
            <div class="space-y-4">
                <CountdownTimer :at="cutoff.at" :is-past="cutoff.is_past" :time="cutoff.time" />

                <Card title="Quick things">
                    <div class="grid grid-cols-2 gap-2">
                        <Link
                            v-for="link in quickLinks"
                            :key="link.href"
                            :href="link.href"
                            class="flex min-h-touch items-center rounded-control border border-line px-3 text-body text-ink transition hover:border-primary hover:bg-primary-light"
                        >
                            {{ link.label }}
                        </Link>
                    </div>
                </Card>

                <InstallPrompt />
            </div>
        </div>
    </BranchLayout>
</template>
