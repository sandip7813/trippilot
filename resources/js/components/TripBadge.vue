<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

export type TripBadgeTone =
    'neutral' | 'emerald' | 'amber' | 'sky' | 'violet' | 'teal' | 'rose';

const props = withDefaults(
    defineProps<{
        tone?: TripBadgeTone;
        icon?: Component;
        dot?: boolean;
        size?: 'sm' | 'md';
        class?: string;
    }>(),
    { tone: 'neutral', size: 'md' },
);

const toneClasses: Record<TripBadgeTone, { badge: string; dot: string }> = {
    neutral: {
        badge: 'border-border bg-muted/60 text-foreground/80',
        dot: 'bg-slate-400',
    },
    emerald: {
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300',
        dot: 'bg-emerald-500',
    },
    amber: {
        badge: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
    sky: {
        badge: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/25 dark:bg-sky-500/10 dark:text-sky-300',
        dot: 'bg-sky-500',
    },
    violet: {
        badge: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/25 dark:bg-violet-500/10 dark:text-violet-300',
        dot: 'bg-violet-500',
    },
    teal: {
        badge: 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-500/25 dark:bg-teal-500/10 dark:text-teal-300',
        dot: 'bg-teal-500',
    },
    rose: {
        badge: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300',
        dot: 'bg-rose-500',
    },
};
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-md border font-medium whitespace-nowrap shadow-[0_1px_0_0_rgb(0_0_0/0.02)]',
                size === 'sm'
                    ? 'px-1.5 py-0.5 text-[11px]'
                    : 'px-2.5 py-1 text-xs',
                toneClasses[tone].badge,
                props.class,
            )
        "
    >
        <span v-if="dot" class="relative flex size-1.5">
            <span
                class="absolute inset-0 rounded-full"
                :class="toneClasses[tone].dot"
            />
        </span>
        <component
            :is="icon"
            v-else-if="icon"
            :class="size === 'sm' ? 'size-3' : 'size-3.5'"
            class="shrink-0 opacity-80"
        />
        <slot />
    </span>
</template>
