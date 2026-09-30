<script setup lang="ts">
import { CircleCheck } from '@lucide/vue';

defineProps<{
    message?: string | null;
}>();
</script>

<template>
    <div
        v-if="message"
        role="status"
        class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200"
    >
        <CircleCheck class="mt-0.5 size-4 shrink-0" />
        <p>{{ message }}</p>
    </div>
</template>
