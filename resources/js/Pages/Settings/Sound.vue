<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Volume2 } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchLayout from '@/Layouts/BranchLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import SwitchField from '@/Components/ui/SwitchField.vue';
import { useSound } from '@/Composables/useSound';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const Layout = computed(() => (user.value.is_admin_side ? AdminLayout : BranchLayout));

const sound = useSound();

const form = useForm({
    sound_enabled: user.value.sound_enabled ?? true,
    sound_volume: user.value.sound_volume ?? 80,
});

// Changing the slider should be heard immediately, not after saving.
watch(
    () => [form.sound_enabled, form.sound_volume],
    ([enabled, volume]) => sound.configure({ enabled, volume }),
);

const samples = [
    { key: 'new_request', label: 'New request arrived', who: 'Main store hears this' },
    { key: 'approved', label: 'Approved in full', who: 'Branch hears this' },
    { key: 'partial', label: 'Approved in part', who: 'Branch hears this' },
    { key: 'rejected', label: 'Not approved', who: 'Branch hears this' },
    { key: 'sent', label: 'Goods sent', who: 'Branch hears this' },
    { key: 'low_stock', label: 'Stock running low', who: 'Main store hears this' },
    { key: 'failed', label: 'Something went wrong', who: 'Whoever tried' },
];
</script>

<template>
    <component :is="Layout" title="Sound" :back="user.is_admin_side ? undefined : '/b/more'">
        <Head title="Sound" />

        <div class="max-w-xl space-y-4">
            <div
                v-if="sound.state.blocked && form.sound_enabled"
                class="rounded-card border border-waiting/20 bg-waiting-bg p-card text-body text-waiting"
            >
                Your browser has not let sound play yet. Tap anywhere on the screen once and it will
                work for the rest of the day.
            </div>

            <section class="space-y-4 rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <SwitchField
                    v-model="form.sound_enabled"
                    label="Play a sound for alerts"
                    hint="You still see a message and a number on the tab either way."
                />

                <div :class="form.sound_enabled ? '' : 'opacity-50'">
                    <label for="volume" class="mb-2 block text-body text-ink">
                        How loud · <span class="tabular">{{ form.sound_volume }}%</span>
                    </label>

                    <div class="flex items-center gap-3">
                        <Volume2 :size="20" class="shrink-0 text-ink-soft" aria-hidden="true" />
                        <input
                            id="volume"
                            v-model.number="form.sound_volume"
                            type="range"
                            min="0"
                            max="100"
                            step="5"
                            :disabled="!form.sound_enabled"
                            class="h-2 w-full cursor-pointer appearance-none rounded-full bg-line accent-primary"
                        />
                    </div>

                    <AppButton
                        variant="secondary"
                        class="mt-3"
                        :disabled="!form.sound_enabled"
                        @click="sound.preview()"
                    >
                        Play a test sound
                    </AppButton>
                </div>

                <div class="flex justify-end border-t border-line pt-4">
                    <AppButton
                        :loading="form.processing"
                        loading-text="Saving…"
                        @click="form.put(route('settings.sound'), { preserveScroll: true })"
                    >
                        Save
                    </AppButton>
                </div>
            </section>

            <section class="rounded-card border border-line bg-surface p-card lg:p-card-lg">
                <h2 class="text-heading text-ink">What each sound means</h2>
                <p class="mt-1 text-helper text-ink-soft">Tap one to hear it.</p>

                <div class="mt-4 divide-y divide-line">
                    <button
                        v-for="sample in samples"
                        :key="sample.key"
                        type="button"
                        class="flex min-h-touch w-full items-center gap-3 py-3 text-left"
                        @click="sound.play(sample.key)"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block text-body text-ink">{{ sample.label }}</span>
                            <span class="block text-helper text-ink-soft">{{ sample.who }}</span>
                        </span>
                        <Volume2 :size="20" class="shrink-0 text-primary" aria-hidden="true" />
                    </button>
                </div>
            </section>
        </div>
    </component>
</template>
