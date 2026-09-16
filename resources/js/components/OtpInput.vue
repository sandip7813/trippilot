<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        length?: number;
        name: string;
        id?: string;
        disabled?: boolean;
        autofocus?: boolean;
    }>(),
    {
        length: 6,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const digits = ref<string[]>(splitValue(props.modelValue));
const boxRefs = ref<HTMLInputElement[]>([]);

function splitValue(value: string): string[] {
    const chars = value.replace(/\D/g, '').slice(0, props.length).split('');

    return Array.from(
        { length: props.length },
        (_, index) => chars[index] ?? '',
    );
}

watch(
    () => props.modelValue,
    (value) => {
        if (value === digits.value.join('')) {
            return;
        }

        digits.value = splitValue(value);
    },
);

function setBoxRef(el: Element | null, index: number): void {
    if (el instanceof HTMLInputElement) {
        boxRefs.value[index] = el;
    }
}

function focusBox(index: number): void {
    boxRefs.value[index]?.focus();
    boxRefs.value[index]?.select();
}

function handleInput(index: number, event: Event): void {
    const target = event.target as HTMLInputElement;
    const value = target.value.replace(/\D/g, '');

    if (!value) {
        digits.value[index] = '';
        emitValue();

        return;
    }

    digits.value[index] = value.slice(-1);
    emitValue();

    if (index < props.length - 1) {
        void nextTick(() => focusBox(index + 1));
    }
}

function handleKeydown(index: number, event: KeyboardEvent): void {
    if (
        event.key.length === 1 &&
        !/^\d$/.test(event.key) &&
        !event.ctrlKey &&
        !event.metaKey
    ) {
        event.preventDefault();

        return;
    }

    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        event.preventDefault();
        digits.value[index - 1] = '';
        emitValue();
        focusBox(index - 1);

        return;
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        focusBox(index - 1);
    }

    if (event.key === 'ArrowRight' && index < props.length - 1) {
        event.preventDefault();
        focusBox(index + 1);
    }
}

function handlePaste(index: number, event: ClipboardEvent): void {
    const pasted = event.clipboardData?.getData('text') ?? '';
    const chars = pasted.replace(/\D/g, '').split('');

    if (!chars.length) {
        return;
    }

    event.preventDefault();

    chars.slice(0, props.length - index).forEach((char, offset) => {
        digits.value[index + offset] = char;
    });

    emitValue();

    const nextIndex = Math.min(index + chars.length, props.length - 1);
    void nextTick(() => focusBox(nextIndex));
}

function handleFocus(event: FocusEvent): void {
    (event.target as HTMLInputElement).select();
}

function emitValue(): void {
    emit('update:modelValue', digits.value.join(''));
}

const hiddenValue = computed(() => digits.value.join(''));
</script>

<template>
    <div class="flex gap-2" role="group" aria-label="Verification code">
        <input type="hidden" :name="name" :value="hiddenValue" />
        <input
            v-for="(digit, index) in digits"
            :key="index"
            :ref="(el) => setBoxRef(el, index)"
            :id="index === 0 ? id : undefined"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="one-time-code"
            maxlength="1"
            :value="digit"
            :disabled="disabled"
            :autofocus="autofocus && index === 0"
            class="h-12 w-10 rounded-md border border-input bg-background text-center text-lg font-semibold shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            @input="handleInput(index, $event)"
            @keydown="handleKeydown(index, $event)"
            @paste="handlePaste(index, $event)"
            @focus="handleFocus"
        />
    </div>
</template>
