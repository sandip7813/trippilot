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
                    'shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                    active === index
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                )
            "
            @click="active = index"
        >
            {{ label }}
        </button>
    </div>
</template>
