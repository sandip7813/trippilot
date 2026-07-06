<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import TripPilotLogoMark from '@/components/logos/TripPilotLogoMark.vue';
import type { LogoVariant } from '@/config/brand';

type Props = {
    class?: HTMLAttributes['class'];
    showTagline?: boolean;
    variant?: 'default' | 'light';
    size?: 'sm' | 'md' | 'lg';
    logo?: LogoVariant;
};

withDefaults(defineProps<Props>(), {
    showTagline: false,
    variant: 'default',
    size: 'md',
});

const markSizeClasses = {
    sm: 'size-8',
    md: 'size-10',
    lg: 'size-12',
};

const textClasses = {
    sm: 'text-base',
    md: 'text-xl',
    lg: 'text-2xl',
};
</script>

<template>
    <div :class="['flex items-center gap-3', $props.class]">
        <TripPilotLogoMark
            :class="markSizeClasses[size]"
            :variant="variant"
            :logo="logo"
        />
        <div v-if="showTagline || $slots.default" class="flex flex-col leading-tight">
            <span
                :class="[
                    'font-bold tracking-tight',
                    textClasses[size],
                    variant === 'light' ? 'text-white' : 'text-foreground',
                ]"
            >
                TripPilot
            </span>
            <span
                v-if="showTagline"
                :class="[
                    'text-xs font-medium',
                    variant === 'light' ? 'text-white/75' : 'text-muted-foreground',
                ]"
            >
                Plan smarter. Travel further.
            </span>
            <slot />
        </div>
    </div>
</template>
