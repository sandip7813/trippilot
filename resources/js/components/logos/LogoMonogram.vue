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
const grad = computed(() => `mono-grad-${uid}`);
const gradLight = computed(() => `mono-grad-light-${uid}`);
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
            <linearGradient :id="grad" x1="8" y1="40" x2="40" y2="8">
                <stop offset="0%" stop-color="#0d9488" />
                <stop offset="100%" stop-color="#38bdf8" />
            </linearGradient>
            <linearGradient :id="gradLight" x1="8" y1="40" x2="40" y2="8">
                <stop offset="0%" stop-color="#ffffff" />
                <stop offset="100%" stop-color="#bae6fd" />
            </linearGradient>
        </defs>
        <!-- Rounded square background (transparent outside — shape only) -->
        <rect
            x="6"
            y="6"
            width="36"
            height="36"
            rx="10"
            :fill="variant === 'light' ? `url(#${gradLight})` : `url(#${grad})`"
            opacity="0.15"
        />
        <!-- T -->
        <path
            d="M14 16 H26 V19 H22 V32 H18 V19 H14 Z"
            :fill="variant === 'light' ? '#ffffff' : '#0d9488'"
        />
        <!-- P -->
        <path
            d="M28 16 H34 C36.2 16 38 17.8 38 20 C38 22.2 36.2 24 34 24 H32 V32 H28 Z M32 19 V21 H34 C34.6 21 35 20.6 35 20 C35 19.4 34.6 19 34 19 Z"
            :fill="variant === 'light' ? '#bae6fd' : '#38bdf8'"
        />
    </svg>
</template>
