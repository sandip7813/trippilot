<script setup lang="ts">
import { computed, useId } from 'vue';
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

type Props = {
    className?: HTMLAttributes['class'];
    variant?: 'default' | 'light';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});

const uid = useId();
const grad = computed(() => `pin-grad-${uid}`);
const gradLight = computed(() => `pin-grad-light-${uid}`);
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
            <linearGradient :id="grad" x1="10" y1="42" x2="38" y2="10">
                <stop offset="0%" stop-color="#0d9488" />
                <stop offset="100%" stop-color="#38bdf8" />
            </linearGradient>
            <linearGradient :id="gradLight" x1="10" y1="42" x2="38" y2="10">
                <stop offset="0%" stop-color="#ffffff" />
                <stop offset="100%" stop-color="#bae6fd" />
            </linearGradient>
        </defs>
        <!-- Orbit ring -->
        <ellipse
            cx="24"
            cy="22"
            rx="17"
            ry="7"
            :stroke="variant === 'light' ? '#ffffff' : '#38bdf8'"
            stroke-width="1.5"
            stroke-dasharray="4 3"
            opacity="0.55"
            transform="rotate(-15 24 22)"
        />
        <!-- Pin body -->
        <path
            d="M24 6 C17 6 12 12 12 19 C12 28 24 42 24 42 C24 42 36 28 36 19 C36 12 31 6 24 6 Z"
            :fill="variant === 'light' ? `url(#${gradLight})` : `url(#${grad})`"
        />
        <!-- Inner circle -->
        <circle
            cx="24"
            cy="18"
            r="6"
            :fill="variant === 'light' ? '#0d9488' : '#ffffff'"
            opacity="0.9"
        />
        <circle
            cx="24"
            cy="18"
            r="2.5"
            :fill="variant === 'light' ? '#ffffff' : '#0d9488'"
        />
    </svg>
</template>
