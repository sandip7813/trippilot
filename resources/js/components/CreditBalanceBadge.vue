<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Coins } from '@lucide/vue';
import { useCredits } from '@/composables/useCredits';
import { history as creditHistory } from '@/routes/credits';

const { credits } = useCredits();
</script>

<template>
    <Link
        v-if="credits"
        :href="creditHistory()"
        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
        :aria-label="`${credits.balance} credits available`"
        data-test="credit-balance-badge"
    >
        <Coins class="size-4" />
        <span class="tabular-nums">{{ credits.balance }}</span>
        <span class="hidden sm:inline">credits</span>
    </Link>
</template>
