<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { normalizeBudgetBreakdown } from '@/lib/budget';
import type { Trip } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
}>();

const page = usePage();

const appCurrency = computed(
    () => (page.props.currency as { code?: string } | undefined)?.code ?? 'INR',
);

const budget = computed(() =>
    normalizeBudgetBreakdown(
        props.trip.itinerary?.budget_breakdown as Record<string, unknown> | undefined,
        appCurrency.value,
    ),
);

const needsBudgetRegeneration = computed(
    () => budget.value.estimatedTotal !== null && ! budget.value.hasLineItems,
);
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="text-base">Budget breakdown</CardTitle>
            <p class="text-xs text-muted-foreground">
                AI estimate from your itinerary — separate from your planned budget above.
            </p>
        </CardHeader>
        <CardContent class="space-y-3">
            <dl
                v-if="budget.lines.length"
                class="space-y-2 text-sm"
            >
                <div
                    v-for="line in budget.lines"
                    :key="line.label"
                    class="flex items-center justify-between gap-4 border-b border-border/40 pb-2 last:border-b-0 last:pb-0"
                >
                    <dt class="text-muted-foreground">{{ line.label }}</dt>
                    <dd class="font-medium">{{ line.amount }}</dd>
                </div>
            </dl>
            <p
                v-if="budget.estimatedTotal && budget.hasLineItems"
                class="flex items-center justify-between gap-4 border-t border-border/60 pt-3 text-sm font-semibold"
            >
                <span>Estimated total</span>
                <span>{{ budget.estimatedTotal }}</span>
            </p>
            <p
                v-else-if="budget.estimatedTotal"
                class="text-lg font-semibold"
            >
                Estimated total: {{ budget.estimatedTotal }}
            </p>
            <p
                v-if="needsBudgetRegeneration"
                class="text-sm text-muted-foreground"
            >
                Category breakdown was not saved for this itinerary. Regenerate to refresh accommodation, food, transport, and other line items.
            </p>
        </CardContent>
    </Card>
</template>
