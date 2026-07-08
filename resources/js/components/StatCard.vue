<script setup lang="ts">
import type { Component } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    icon?: Component;
    accent?: 'primary' | 'sky' | 'amber' | 'violet';
}>();

const accentClasses = {
    primary: {
        icon: 'brand-gradient text-white shadow-md shadow-teal-500/20',
        bar: 'from-teal-500 to-cyan-500',
    },
    sky: {
        icon: 'bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-md shadow-sky-500/20',
        bar: 'from-sky-500 to-blue-600',
    },
    amber: {
        icon: 'bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-md shadow-amber-500/20',
        bar: 'from-amber-500 to-orange-500',
    },
    violet: {
        icon: 'bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-md shadow-violet-500/20',
        bar: 'from-violet-500 to-purple-600',
    },
};
</script>

<template>
    <Card class="card-vibrant overflow-hidden">
        <div
            class="h-1 bg-gradient-to-r"
            :class="accentClasses[accent ?? 'primary'].bar"
        />
        <CardContent class="flex items-start gap-4 p-5">
            <div
                v-if="icon"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                :class="accentClasses[accent ?? 'primary'].icon"
            >
                <component :is="icon" class="size-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-muted-foreground">
                    {{ label }}
                </p>
                <p class="mt-1 text-2xl font-bold tracking-tight">
                    {{ value }}
                </p>
                <p v-if="hint" class="mt-1 text-xs text-muted-foreground">
                    {{ hint }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
