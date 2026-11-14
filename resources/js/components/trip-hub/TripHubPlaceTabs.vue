<script setup lang="ts">
import { cn } from '@/lib/utils';

defineProps<{
    labels: string[];
}>();

const active = defineModel<number>('active', { required: true });
</script>

<template>
    <div class="flex gap-1 overflow-x-auto pt-2" role="tablist">
        <button
            v-for="(label, index) in labels"
            :key="index"
            type="button"
            role="tab"
            :aria-selected="active === index"
            :class="
                cn(
                    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-all duration-200',
                    active === index
                        ? 'border-transparent bg-gradient-to-r from-teal-500 to-violet-500 text-white shadow-md shadow-violet-500/25'
                        : 'border-border/70 bg-background/70 text-muted-foreground hover:border-primary/40 hover:text-foreground',
                )
            "
            @click="active = index"
        >
            {{ label }}
        </button>
    </div>
</template>
