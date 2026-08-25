<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import BrandMark from '@/Components/ui/BrandMark.vue';

/**
 * Every error page says what happened and what to do next. None of them show
 * a number on its own, and none of them apologise at length.
 */
const props = defineProps({
    status: { type: Number, required: true },
});

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth?.user));

const messages = {
    403: {
        title: 'That is not yours to open',
        body: 'This page belongs to someone else at the restaurant. If you think you should be able to see it, ask your admin.',
    },
    404: {
        title: 'That page is not here',
        body: 'The link may be old, or the thing it pointed at may have been cancelled.',
    },
    419: {
        title: 'You were away too long',
        body: 'Sign in again and carry on. Nothing you sent has been lost.',
    },
    429: {
        title: 'Too many tries',
        body: 'Wait a minute and try again.',
    },
    500: {
        title: 'Something broke at our end',
        body: 'This is not your fault. Try again in a moment - and tell your admin if it keeps happening.',
    },
    503: {
        title: 'Back in a few minutes',
        body: 'The app is being updated. Nothing has been lost.',
    },
};

const message = computed(
    () =>
        messages[props.status] ?? {
            title: 'Something went wrong',
            body: 'Try again in a moment.',
        },
);

const homeUrl = computed(() => (signedIn.value ? '/home' : '/login'));
</script>

<template>
    <div class="flex min-h-dvh items-center justify-center bg-page px-6 py-10">
        <Head :title="message.title" />

        <div class="w-full max-w-md rounded-card border border-line bg-surface p-6 text-center">
            <div class="flex justify-center">
                <BrandMark size="md" :show-name="false" />
            </div>

            <h1 class="mt-5 text-title text-ink">{{ message.title }}</h1>
            <p class="mt-2 text-body text-ink-soft">{{ message.body }}</p>

            <Link
                :href="homeUrl"
                class="mt-6 inline-flex min-h-control items-center justify-center gap-2 rounded-control bg-primary px-5 text-body font-medium text-white"
            >
                <ArrowLeft :size="20" />
                {{ signedIn ? 'Back to the app' : 'Go to sign in' }}
            </Link>
        </div>
    </div>
</template>
