<script setup lang="ts">
import type { Component } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    title: string;
    description: string;
    icon?: Component;
}>();
</script>

<template>
    <Card class="border-dashed border-sidebar-border/70 dark:border-sidebar-border">
        <CardContent class="flex flex-col items-center justify-center px-6 py-16 text-center">
            <div
                v-if="icon"
                class="mb-4 flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary"
            >
                <component :is="icon" class="size-7" />
            </div>
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <p class="mt-2 max-w-md text-sm text-muted-foreground">
                {{ description }}
            </p>
            <div v-if="$slots.actions" class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <slot name="actions" />
            </div>
        </CardContent>
    </Card>
</template>
