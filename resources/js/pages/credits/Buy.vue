<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Info, ShoppingCart } from '@lucide/vue';
import BuyCreditPacks from '@/components/BuyCreditPacks.vue';
import CreditBalanceSummary from '@/components/CreditBalanceSummary.vue';
import PageHeader from '@/components/PageHeader.vue';
import { buy as buyCredits } from '@/routes/credits';
import type { CreditPackOption } from '@/types/credits';

defineProps<{
    balance: number;
    charging_enabled: boolean;
    is_exempt: boolean;
    unavailable_reason: string | null;
    packs: CreditPackOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Buy credits', href: buyCredits() }],
    },
});
</script>

<template>
    <Head title="Buy credits" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Buy credits"
            description="One-time packs. Bigger packs give you more credits per rupee."
            :icon="ShoppingCart"
        />

        <CreditBalanceSummary
            :balance="balance"
            :charging-enabled="charging_enabled"
            :is-exempt="is_exempt"
            show-never-expire
        />

        <p
            v-if="unavailable_reason"
            class="flex items-start gap-2 rounded-2xl border border-border/60 bg-card p-4 text-sm text-muted-foreground"
            data-test="purchase-unavailable"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            {{ unavailable_reason }}
        </p>

        <p
            v-else-if="packs.length === 0"
            class="rounded-2xl border border-border/60 bg-card p-4 text-sm text-muted-foreground"
        >
            No credit packs are on sale right now. Please check back soon.
        </p>

        <BuyCreditPacks v-else :packs="packs" />
    </div>
</template>
