<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { ContactMessageStatus } from '@/types/admin';

defineProps<{
    status: ContactMessageStatus;
    label: string;
}>();

const statusClasses: Record<ContactMessageStatus, string> = {
    new: 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:ring-sky-900',
    replied:
        'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-900',
    closed: 'bg-muted text-muted-foreground ring-border',
};
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                statusClasses[status],
            )
        "
    >
        <span
            v-if="status === 'new'"
            class="size-1.5 rounded-full bg-current"
        />
        {{ label }}
    </span>
</template>
