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
const grad = computed(() => `globe-grad-${uid}`);
const gradLight = computed(() => `globe-grad-light-${uid}`);
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
            <linearGradient :id="grad" x1="6" y1="42" x2="42" y2="6">
                <stop offset="0%" stop-color="#0d9488" />
                <stop offset="100%" stop-color="#38bdf8" />
            </linearGradient>
            <linearGradient :id="gradLight" x1="6" y1="42" x2="42" y2="6">
                <stop offset="0%" stop-color="#ffffff" />
                <stop offset="100%" stop-color="#bae6fd" />
            </linearGradient>
        </defs>
        <!-- Globe -->
        <circle
            cx="24"
            cy="24"
            r="16"
            :stroke="variant === 'light' ? `url(#${gradLight})` : `url(#${grad})`"
            stroke-width="2.5"
        />
        <!-- Meridians -->
        <ellipse
            cx="24"
            cy="24"
            rx="7"
            ry="16"
            :stroke="variant === 'light' ? '#ffffff' : '#14b8a6'"
            stroke-width="1.5"
            opacity="0.5"
        />
        <path
            d="M8 24 H40"
            :stroke="variant === 'light' ? '#ffffff' : '#14b8a6'"
            stroke-width="1.5"
            opacity="0.5"
        />
        <path
            d="M10 16 H38 M10 32 H38"
            :stroke="variant === 'light' ? '#ffffff' : '#14b8a6'"
            stroke-width="1"
            opacity="0.35"
        />
        <!-- Flight arc -->
        <path
            d="M10 34 C 18 14, 30 10, 40 16"
            :stroke="variant === 'light' ? '#ffffff' : '#38bdf8'"
            stroke-width="2"
            stroke-linecap="round"
            fill="none"
        />
        <!-- Plane dot -->
        <circle
            cx="40"
            cy="16"
            r="2.5"
            :fill="variant === 'light' ? '#ffffff' : '#38bdf8'"
        />
    </svg>
</template>
