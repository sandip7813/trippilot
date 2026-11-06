<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Calendar, Train, Users, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import { formatDisplayDateRange } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import type { TripTrainTimings } from '@/types/train';
import type { Trip } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
    trainTimings?: TripTrainTimings | null;
}>();

const page = usePage();

const appCurrency = computed(
    () =>
        (page.props.currency as
            { code?: string; locale?: string } | undefined) ?? {
            code: 'INR',
            locale: 'en-IN',
        },
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

const dateRange = computed(() =>
    formatDisplayDateRange(props.trip.start_date, props.trip.end_date),
);

const transportHint = computed((): string => {
    if (props.trip.route_mode === 'multi_city') {
        const legs =
            props.trainTimings?.leg_count ??
            props.trip.route_summary?.leg_count ??
            0;
        const cities = props.trip.route_summary?.stop_count ?? 0;

        if (cities > 0) {
            return `${cities} cities · ${legs} travel legs below itinerary`;
        }

        return 'Multi-city route below itinerary';
    }

    if (props.trip.trip_scope === 'domestic') {
        if (props.trainTimings?.available) {
            const legCount =
                props.trainTimings.leg_count ??
                props.trainTimings.legs?.length ??
                0;
            const outboundCount = props.trainTimings.outbound?.count ?? 0;
            const returnCount = props.trainTimings.return?.count ?? 0;
            const total = legCount > 0 ? legCount : outboundCount + returnCount;

            if (total > 0) {
                if (legCount > 2) {
                    return `${legCount} train legs below itinerary`;
                }

                const parts = [];

                if (outboundCount > 0) {
                    parts.push(
                        `${outboundCount} outbound train${outboundCount === 1 ? '' : 's'}`,
                    );
                }

                if (returnCount > 0) {
                    parts.push(
                        `${returnCount} return train${returnCount === 1 ? '' : 's'}`,
                    );
                }

                return `${parts.join(' · ')} below itinerary`;
            }
        }

        if (props.trainTimings?.message) {
            return props.trainTimings.message;
        }

        return 'Indian rail options below itinerary';
    }

    if (props.trip.trip_scope === 'international') {
        return 'See the Airports and Public transport tabs';
    }

    return 'Set a mapped destination';
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm sm:col-span-2"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-sky-500/15 text-sky-600 dark:text-sky-400"
            >
                <Calendar class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Dates
                </p>
                <p class="text-sm leading-snug font-semibold">
                    {{ dateRange }}
                </p>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-violet-500/15 text-violet-600 dark:text-violet-400"
            >
                <Users class="size-4" />
            </span>
            <div>
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Travelers
                </p>
                <p class="text-sm font-semibold">{{ trip.travelers }}</p>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
            >
                <Wallet class="size-4" />
            </span>
            <div class="min-w-0">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Budget
                </p>
                <p class="text-sm leading-snug font-semibold">
                    {{ formattedBudget }}
                </p>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-lg border border-border/60 bg-card/80 p-3 shadow-sm sm:col-span-2"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-md bg-amber-500/15 text-amber-600 dark:text-amber-400"
            >
                <Train class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Transport
                </p>
                <p class="text-sm leading-snug text-muted-foreground">
                    {{ transportHint }}
                </p>
            </div>
        </div>
    </div>
</template>
