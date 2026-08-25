<script setup>
import { computed, ref, useId } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

/**
 * The label sits above the field, not floating inside it.
 *
 * A floating label has to be positioned against an exact field height and font
 * size, and it collides with the value the moment either changes - which is
 * what happened when the type scale came down. It also disappears the instant
 * someone starts typing, which is the worst time to hide what a field is for.
 * SelectField already labelled this way; now everything does.
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    inputmode: { type: String, default: null },
    autocomplete: { type: String, default: null },
    placeholder: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const id = useId();
const showPassword = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && showPassword.value ? 'text' : props.type));
const input = ref(null);

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-helper text-ink-soft">
            {{ label }}
        </label>

        <div class="relative">
            <input
                :id="id"
                ref="input"
                :type="inputType"
                :inputmode="inputmode"
                :autocomplete="autocomplete"
                :placeholder="placeholder ?? undefined"
                :required="required"
                :autofocus="autofocus"
                :disabled="disabled"
                :value="modelValue"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
                class="min-h-control w-full rounded-control border bg-surface px-4 text-body text-ink transition placeholder:text-ink-muted focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0 disabled:bg-page disabled:text-ink-soft"
                :class="[error ? 'border-rejected' : 'border-line', isPassword ? 'pr-12' : '']"
                @input="emit('update:modelValue', $event.target.value)"
            />

            <button
                v-if="isPassword"
                type="button"
                class="absolute right-0 top-1/2 flex h-touch w-touch -translate-y-1/2 items-center justify-center rounded-control text-ink-soft transition hover:text-ink"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                @click="showPassword = !showPassword"
            >
                <component :is="showPassword ? EyeOff : Eye" :size="18" />
            </button>
        </div>

        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-helper text-rejected">
            {{ error }}
        </p>
        <p v-else-if="hint" :id="`${id}-hint`" class="mt-1.5 text-helper text-ink-soft">
            {{ hint }}
        </p>
    </div>
</template>
