<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ListChecks } from '@lucide/vue';
import CreditBalanceSummary from '@/components/CreditBalanceSummary.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatCredits } from '@/composables/useCredits';
import { costs as creditCosts } from '@/routes/credits';
import type { CreditServiceCost } from '@/types/credits';

defineProps<{
    balance: number;
    charging_enabled: boolean;
    is_exempt: boolean;
    costs: CreditServiceCost[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Credit costs', href: creditCosts() }],
    },
});
</script>

<template>
    <Head title="Credit costs" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Credit costs"
            description="How many credits each AI service uses. If an AI request fails, the credits are returned automatically."
            :icon="ListChecks"
        />

        <CreditBalanceSummary
            :balance="balance"
            :charging-enabled="charging_enabled"
            :is-exempt="is_exempt"
            show-never-expire
        />

        <Card>
            <CardContent class="p-0">
                <ul class="divide-y divide-border/60">
                    <li
                        v-for="cost in costs"
                        :key="cost.service"
                        class="flex items-center justify-between gap-4 px-4 py-3 text-sm sm:px-6"
                        data-test="credit-cost"
                    >
                        <span>{{ cost.label }}</span>
                        <span class="font-semibold tabular-nums">
                            {{
                                cost.credits === 0
                                    ? 'Free'
                                    : formatCredits(cost.credits)
                            }}
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between gap-4 px-4 py-3 text-sm sm:px-6"
                    >
                        <span>Trip cover photo (automatic or uploaded)</span>
                        <span class="font-semibold">Free</span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
