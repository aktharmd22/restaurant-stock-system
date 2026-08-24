<script setup>
import { computed, ref, useId } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    inputmode: { type: String, default: null },
    autocomplete: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
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
        <div class="relative">
            <input
                :id="id"
                ref="input"
                :type="inputType"
                :inputmode="inputmode"
                :autocomplete="autocomplete"
                :required="required"
                :autofocus="autofocus"
                :value="modelValue"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
                placeholder=" "
                class="peer min-h-control w-full rounded-control border bg-surface px-4 pb-2 pt-6 text-body text-ink transition placeholder:text-transparent focus:border-primary focus:shadow-focus focus:outline-none focus:ring-0"
                :class="[
                    error ? 'border-rejected' : 'border-line',
                    isPassword ? 'pr-14' : '',
                ]"
                @input="emit('update:modelValue', $event.target.value)"
            />

            <label
                :for="id"
                class="pointer-events-none absolute left-4 top-2 text-helper text-ink-soft transition-all peer-placeholder-shown:top-1/2 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:text-body peer-focus:top-2 peer-focus:translate-y-0 peer-focus:text-helper peer-focus:text-primary"
            >
                {{ label }}
            </label>

            <button
                v-if="isPassword"
                type="button"
                class="absolute right-1 top-1/2 flex h-touch w-touch -translate-y-1/2 items-center justify-center rounded-control text-ink-soft hover:text-ink"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                @click="showPassword = !showPassword"
            >
                <component :is="showPassword ? EyeOff : Eye" :size="20" />
            </button>
        </div>

        <p v-if="error" :id="`${id}-error`" class="mt-2 text-helper text-rejected">
            {{ error }}
        </p>
        <p v-else-if="hint" :id="`${id}-hint`" class="mt-2 text-helper text-ink-soft">
            {{ hint }}
        </p>
    </div>
</template>
