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
    <Card class="card-vibrant overflow-hidden border-dashed">
        <div class="brand-gradient h-1 opacity-80" />
        <CardContent class="flex flex-col items-center justify-center px-6 py-16 text-center">
            <div
                v-if="icon"
                class="brand-gradient mb-5 flex size-16 items-center justify-center rounded-2xl text-white shadow-lg shadow-teal-500/25"
            >
                <component :is="icon" class="size-8" />
            </div>
            <h2 class="text-xl font-bold tracking-tight">{{ title }}</h2>
            <p class="mt-2 max-w-md text-sm leading-relaxed text-muted-foreground">
                {{ description }}
            </p>
            <div v-if="$slots.actions" class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <slot name="actions" />
            </div>
        </CardContent>
    </Card>
</template>
