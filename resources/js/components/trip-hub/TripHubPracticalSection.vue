<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TripHubBudgetBreakdown from '@/components/trip-hub/TripHubBudgetBreakdown.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { normalizeBudgetBreakdown } from '@/lib/budget';
import type { Trip } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
}>();

const page = usePage();

const packingList = computed(() => props.trip.itinerary?.packing_list ?? []);

const appCurrency = computed(
    () => (page.props.currency as { code?: string } | undefined)?.code ?? 'INR',
);

const hasBudgetBreakdown = computed(() => {
    if ((props.trip.itinerary?.days?.length ?? 0) === 0) {
        return false;
    }

    const budget = normalizeBudgetBreakdown(
        props.trip.itinerary?.budget_breakdown as Record<string, unknown> | undefined,
        appCurrency.value,
    );

    return budget.hasLineItems || budget.estimatedTotal !== null;
});
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-2">
        <Card v-if="trip.notes">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Your notes</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground">
                    {{ trip.notes }}
                </p>
            </CardContent>
        </Card>

        <Card v-if="packingList.length">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Packing list</CardTitle>
            </CardHeader>
            <CardContent>
                <ul class="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                    <li
                        v-for="item in packingList"
                        :key="item"
                    >
                        {{ item }}
                    </li>
                </ul>
            </CardContent>
        </Card>

        <TripHubBudgetBreakdown
            v-if="hasBudgetBreakdown"
            :trip="trip"
        />
    </div>
</template>
