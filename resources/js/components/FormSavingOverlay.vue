<script setup lang="ts">
import { Spinner } from '@/components/ui/spinner';

withDefaults(
    defineProps<{
        show: boolean;
        message?: string;
    }>(),
    {
        message: 'Saving...',
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/50 backdrop-blur-[1px]"
            aria-live="polite"
            aria-busy="true"
        >
            <div
                class="flex flex-col items-center gap-3 rounded-xl border border-border/60 bg-background/95 px-8 py-6 shadow-lg"
            >
                <Spinner class="size-8 text-primary" />
                <p class="text-sm font-medium text-foreground">
                    {{ message }}
                </p>
            </div>
        </div>
    </Teleport>
</template>
