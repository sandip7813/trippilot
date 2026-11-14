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
        class="relative isolate overflow-hidden rounded-2xl border border-white/70 bg-linear-to-br from-teal-50 via-sky-50 to-violet-100 p-5 shadow-lg shadow-sky-500/10 sm:p-6 dark:border-white/10 dark:from-teal-500/15 dark:via-sky-500/10 dark:to-violet-500/20 dark:shadow-black/20"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-20 -right-16 -z-10 size-64 rounded-full bg-fuchsia-300/40 blur-3xl dark:bg-fuchsia-500/20"
        />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -bottom-24 left-1/4 -z-10 size-56 rounded-full bg-cyan-300/40 blur-3xl dark:bg-cyan-500/15"
        />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute top-1/2 right-1/3 -z-10 size-32 rounded-full bg-amber-200/50 blur-2xl dark:bg-amber-400/10"
        />

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-4">
                <div
                    v-if="icon"
                    class="brand-gradient flex size-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-lg ring-4 shadow-teal-500/30 ring-white/70 sm:size-14 dark:ring-white/10"
                >
                    <component :is="icon" class="size-6 sm:size-7" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                        <span class="brand-gradient-text">{{ title }}</span>
                    </h1>
                    <p
                        v-if="description"
                        class="mt-1.5 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
            <div
                v-if="$slots.actions"
                class="flex shrink-0 flex-wrap items-center gap-2"
            >
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
