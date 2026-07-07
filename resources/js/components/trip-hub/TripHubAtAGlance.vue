<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Calendar, MapPin, Train, Users, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDisplayDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import type { Trip } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
}>();

const page = usePage();

const appCurrency = computed(
    () => (page.props.currency as { code?: string; locale?: string } | undefined) ?? { code: 'INR', locale: 'en-IN' },
);

const formattedBudget = computed(() => {
    if (props.trip.budget == null) {
        return '—';
    }

    return formatMoney(props.trip.budget, {
        currency: appCurrency.value.code ?? 'INR',
        locale: appCurrency.value.locale ?? 'en-IN',
    });
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Calendar class="size-4" />
                    Dates
                </CardTitle>
            </CardHeader>
            <CardContent class="text-sm">
                <p>{{ formatDisplayDate(trip.start_date, { weekday: true }) }}</p>
                <p class="text-muted-foreground">
                    to {{ formatDisplayDate(trip.end_date, { weekday: true }) }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Users class="size-4" />
                    Travelers
                </CardTitle>
            </CardHeader>
            <CardContent class="text-2xl font-semibold">
                {{ trip.travelers }}
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Wallet class="size-4" />
                    Budget
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-1">
                <p class="text-xs text-muted-foreground">Your planned budget</p>
                <p class="text-2xl font-semibold">
                    {{ formattedBudget }}
                </p>
            </CardContent>
        </Card>

        <Card class="sm:col-span-2 lg:col-span-2">
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <MapPin class="size-4" />
                    Route
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-1 text-sm">
                <p v-if="locationLabel(trip.origin)">
                    <span class="text-muted-foreground">From:</span>
                    {{ locationLabel(trip.origin) }}
                </p>
                <p>
                    <span class="text-muted-foreground">To:</span>
                    {{ locationLabel(trip.destination) ?? 'Not set' }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Train class="size-4" />
                    Transport
                </CardTitle>
            </CardHeader>
            <CardContent class="text-sm text-muted-foreground">
                <p v-if="trip.trip_scope === 'domestic'">
                    Indian rail and road options will appear here in a future update.
                </p>
                <p v-else-if="trip.trip_scope === 'international'">
                    Flight and local transport tips will appear here in a future update.
                </p>
                <p v-else>
                    Set a mapped destination to unlock transport insights.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
