<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Coins } from '@lucide/vue';
import { cn } from '@/lib/utils';
import { buy as buyCredits } from '@/routes/credits';

defineProps<{
    costLabel: string;
    balance: number;
    canAfford: boolean;
}>();
</script>

<template>
    <div
        :class="
            cn(
                'flex items-start gap-2 rounded-xl p-3 text-sm',
                canAfford
                    ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300'
                    : 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
            )
        "
        data-test="credit-cost-notice"
    >
        <Coins class="mt-0.5 size-4 shrink-0" />
        <p v-if="canAfford">
            This uses <strong>{{ costLabel }}</strong
            >. Your balance is {{ balance }}. If it fails, the credits are
            returned.
        </p>
        <p v-else>
            Not enough credits: this needs <strong>{{ costLabel }}</strong> and
            you have {{ balance }}.
            <Link :href="buyCredits()" class="font-medium underline">
                Buy credits
            </Link>
        </p>
    </div>
</template>
