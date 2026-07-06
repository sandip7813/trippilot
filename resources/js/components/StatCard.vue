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
    primary: 'bg-primary/10 text-primary',
    sky: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    amber: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    violet: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
};
</script>

<template>
    <Card class="border-sidebar-border/70 shadow-sm dark:border-sidebar-border">
        <CardContent class="flex items-start gap-4 p-5">
            <div
                v-if="icon"
                class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                :class="accentClasses[accent ?? 'primary']"
            >
                <component :is="icon" class="size-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight">{{ value }}</p>
                <p v-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
            </div>
        </CardContent>
    </Card>
</template>
