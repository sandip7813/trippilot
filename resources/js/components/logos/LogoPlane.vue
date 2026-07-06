<script setup lang="ts">
import { computed, useId } from 'vue';
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

type Props = {
    className?: HTMLAttributes['class'];
    variant?: 'default' | 'light';
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
});

const uid = useId();
const grad = computed(() => `plane-grad-${uid}`);
const gradLight = computed(() => `plane-grad-light-${uid}`);
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <defs>
            <linearGradient :id="grad" x1="4" y1="44" x2="44" y2="4">
                <stop offset="0%" stop-color="#0d9488" />
                <stop offset="100%" stop-color="#38bdf8" />
            </linearGradient>
            <linearGradient :id="gradLight" x1="4" y1="44" x2="44" y2="4">
                <stop offset="0%" stop-color="#ffffff" />
                <stop offset="100%" stop-color="#bae6fd" />
            </linearGradient>
        </defs>
        <path
            d="M8 36 C 16 20, 28 12, 40 8"
            :stroke="variant === 'light' ? `url(#${gradLight})` : `url(#${grad})`"
            stroke-width="2.5"
            stroke-linecap="round"
            fill="none"
            opacity="0.9"
        />
        <path
            d="M10 32 L28 18 L22 26 L32 28 L10 32 Z"
            :fill="variant === 'light' ? `url(#${gradLight})` : `url(#${grad})`"
        />
        <path
            d="M22 26 L28 18 L26 30 Z"
            :fill="variant === 'light' ? '#ffffff' : '#0f766e'"
            opacity="0.35"
        />
        <circle
            cx="40"
            cy="8"
            r="3"
            :fill="variant === 'light' ? '#ffffff' : '#38bdf8'"
        />
    </svg>
</template>
