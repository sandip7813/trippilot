<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <div
                v-if="icon"
                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
                <component :is="icon" class="size-5" />
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                <p v-if="description" class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>
        <div v-if="$slots.actions" class="mt-3 flex shrink-0 items-center gap-2 sm:mt-0">
            <slot name="actions" />
        </div>
    </div>
</template>
