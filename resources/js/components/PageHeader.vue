<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="flex items-start gap-4">
            <div
                v-if="icon"
                class="brand-gradient flex size-12 shrink-0 items-center justify-center rounded-xl text-white shadow-lg shadow-teal-500/25"
            >
                <component :is="icon" class="size-6" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                    <span class="brand-gradient-text">{{ title }}</span>
                </h1>
                <p
                    v-if="description"
                    class="mt-1.5 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
        </div>
        <div
            v-if="$slots.actions"
            class="mt-3 flex shrink-0 items-center gap-2 sm:mt-0"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
