<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Coins } from '@lucide/vue';
import { computed } from 'vue';
import { useCredits } from '@/composables/useCredits';
import { buy as buyCredits } from '@/routes/credits';
import type { CreditServiceKey } from '@/types/credits';

const props = defineProps<{
    service: CreditServiceKey;
    suffix?: string;
}>();

const { credits, costLabel, canAfford } = useCredits();

const label = computed(() => costLabel(props.service));
const affordable = computed(() => canAfford(props.service));
</script>

<template>
    <p
        v-if="credits && label"
        :class="[
            'inline-flex flex-wrap items-center gap-1 text-xs',
            affordable
                ? 'text-muted-foreground'
                : 'text-amber-700 dark:text-amber-300',
        ]"
        data-test="credit-usage-hint"
    >
        <Coins class="size-3.5" />
        <span>{{ label }}{{ suffix ? ` ${suffix}` : '' }}</span>
        <span>· Balance {{ credits.balance }}</span>
        <Link
            v-if="!affordable"
            :href="buyCredits()"
            class="font-medium underline"
        >
            Not enough credits · Buy more
        </Link>
    </p>
</template>
