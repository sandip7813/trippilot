<script setup lang="ts">
import { BadgeCheck, Coins, Info } from '@lucide/vue';

withDefaults(
    defineProps<{
        balance: number;
        chargingEnabled: boolean;
        isExempt: boolean;
        showNeverExpire?: boolean;
    }>(),
    { showNeverExpire: false },
);
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-center gap-3">
            <span
                class="flex size-10 items-center justify-center rounded-xl bg-linear-to-br from-teal-500 to-emerald-500 text-white shadow-md shadow-teal-500/30"
            >
                <Coins class="size-5" />
            </span>
            <div>
                <p class="text-xs text-muted-foreground">Available balance</p>
                <p
                    class="text-2xl font-bold tracking-tight tabular-nums"
                    data-test="credit-balance"
                >
                    {{ balance }}
                    <span class="text-sm font-normal text-muted-foreground">
                        {{ balance === 1 ? 'credit' : 'credits' }}
                    </span>
                </p>
            </div>
            <span
                v-if="showNeverExpire"
                class="inline-flex items-center gap-1 self-start rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 sm:self-center dark:bg-emerald-500/15 dark:text-emerald-300"
                data-test="credits-never-expire"
            >
                <BadgeCheck class="size-3.5" />
                Credits never expire
            </span>
        </div>

        <p
            v-if="isExempt"
            class="flex items-start gap-2 rounded-xl bg-muted/60 p-3 text-sm text-muted-foreground sm:max-w-sm"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            As an admin, you use AI features without spending credits.
        </p>
        <p
            v-else-if="!chargingEnabled"
            class="flex items-start gap-2 rounded-xl bg-muted/60 p-3 text-sm text-muted-foreground sm:max-w-sm"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            AI features are free for now. Your credits will be used once credit
            charging starts.
        </p>
    </div>
</template>
