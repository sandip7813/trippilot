<script setup lang="ts">
import { Check, Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    {
        value: 'light',
        Icon: Sun,
        label: 'Light',
        caption: 'Bright & airy',
        preview: 'bg-linear-to-br from-amber-100 via-orange-50 to-sky-100',
        chip: 'bg-linear-to-br from-amber-400 to-orange-500 shadow-amber-500/40',
        ring: 'ring-amber-400',
    },
    {
        value: 'dark',
        Icon: Moon,
        label: 'Dark',
        caption: 'Easy on the eyes',
        preview: 'bg-linear-to-br from-slate-900 via-indigo-950 to-violet-900',
        chip: 'bg-linear-to-br from-indigo-500 to-violet-600 shadow-violet-500/40',
        ring: 'ring-violet-500',
    },
    {
        value: 'system',
        Icon: Monitor,
        label: 'System',
        caption: 'Match your device',
        preview: 'bg-linear-to-r from-sky-100 from-50% to-slate-800 to-50%',
        chip: 'bg-linear-to-br from-teal-400 to-cyan-500 shadow-cyan-500/40',
        ring: 'ring-cyan-500',
    },
] as const;
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-3">
        <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            :aria-pressed="appearance === tab.value"
            @click="updateAppearance(tab.value)"
            :class="[
                'group relative flex flex-col overflow-hidden rounded-2xl border bg-card text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg',
                appearance === tab.value
                    ? ['border-transparent shadow-lg ring-2', tab.ring]
                    : 'border-border/60',
            ]"
        >
            <div
                :class="[
                    'relative flex h-24 items-center justify-center',
                    tab.preview,
                ]"
            >
                <span
                    :class="[
                        'flex size-11 items-center justify-center rounded-xl text-white shadow-lg transition-transform group-hover:scale-110',
                        tab.chip,
                    ]"
                >
                    <component :is="tab.Icon" class="size-5" />
                </span>
                <span
                    v-if="appearance === tab.value"
                    class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-white text-emerald-600 shadow-md"
                >
                    <Check class="size-3.5" />
                </span>
            </div>
            <div class="space-y-0.5 p-3">
                <p class="text-sm font-semibold">{{ tab.label }}</p>
                <p class="text-xs text-muted-foreground">{{ tab.caption }}</p>
            </div>
        </button>
    </div>
</template>
