<script setup lang="ts">
import type { Component } from 'vue';

type Accent = 'teal' | 'cyan' | 'violet' | 'amber' | 'coral';

type Props = {
    title: string;
    description?: string;
    icon: Component;
    accent?: Accent;
};

const props = withDefaults(defineProps<Props>(), {
    accent: 'teal',
});

const accentStyles: Record<Accent, { bar: string; chip: string }> = {
    teal: {
        bar: 'from-teal-400 via-emerald-400 to-cyan-400',
        chip: 'bg-linear-to-br from-teal-500 to-emerald-500 shadow-teal-500/30',
    },
    cyan: {
        bar: 'from-cyan-400 via-sky-400 to-blue-400',
        chip: 'bg-linear-to-br from-cyan-500 to-blue-500 shadow-sky-500/30',
    },
    violet: {
        bar: 'from-violet-400 via-purple-400 to-fuchsia-400',
        chip: 'bg-linear-to-br from-violet-500 to-fuchsia-500 shadow-violet-500/30',
    },
    amber: {
        bar: 'from-amber-400 via-orange-400 to-rose-400',
        chip: 'bg-linear-to-br from-amber-500 to-orange-500 shadow-amber-500/30',
    },
    coral: {
        bar: 'from-rose-400 via-red-400 to-orange-400',
        chip: 'bg-linear-to-br from-rose-500 to-orange-500 shadow-rose-500/30',
    },
};
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm transition-shadow hover:shadow-md"
    >
        <div
            :class="['h-1.5 bg-linear-to-r', accentStyles[props.accent].bar]"
        />
        <div class="flex flex-col gap-6 p-5 sm:p-6">
            <header class="flex items-start gap-4">
                <div
                    :class="[
                        'flex size-11 shrink-0 items-center justify-center rounded-xl text-white shadow-lg',
                        accentStyles[props.accent].chip,
                    ]"
                >
                    <component :is="props.icon" class="size-5" />
                </div>
                <div class="min-w-0 space-y-0.5">
                    <h2 class="text-base font-semibold tracking-tight">
                        {{ props.title }}
                    </h2>
                    <p
                        v-if="props.description"
                        class="text-sm text-muted-foreground"
                    >
                        {{ props.description }}
                    </p>
                </div>
            </header>
            <slot />
        </div>
    </section>
</template>
