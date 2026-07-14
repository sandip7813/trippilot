<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { nightsLabel, stopKindLabel } from '@/composables/useTripRouteStops';
import { formatDisplayDate } from '@/lib/dates';
import { cn } from '@/lib/utils';
import type { TripRouteStop } from '@/types/trip';

defineProps<{
    stops: TripRouteStop[];
    compact?: boolean;
}>();

function formatStopDate(date: string | null | undefined): string | null {
    if (!date) {
        return null;
    }

    const formatted = formatDisplayDate(date);

    return formatted === '—' ? null : formatted;
}

function arrivalLabel(stop: TripRouteStop): string | null {
    const arrival = formatStopDate(stop.arrival_date);

    if (!arrival) {
        return null;
    }

    return `Arrive ${arrival}`;
}

function departureLabel(stop: TripRouteStop): string | null {
    const departure = formatStopDate(stop.departure_date);

    if (!departure) {
        return null;
    }

    return `Depart ${departure}`;
}
</script>

<template>
    <ol class="space-y-0">
        <li
            v-for="(stop, index) in stops"
            :key="`stop-${index}-${stop.kind}-${stop.label}`"
            :class="
                cn(
                    'grid items-center gap-x-2 border-b border-border/40 py-2 last:border-b-0',
                    compact
                        ? 'grid-cols-[1.25rem_minmax(0,1fr)_4rem_minmax(5.5rem,auto)] text-xs'
                        : 'grid-cols-[2rem_minmax(0,1fr)_5.5rem_minmax(6.5rem,auto)] gap-x-3 py-2.5 sm:grid-cols-[2rem_minmax(0,1.2fr)_6rem_minmax(7.5rem,auto)]',
                )
            "
        >
            <span
                :class="
                    cn(
                        'flex shrink-0 items-center justify-center rounded-full border font-semibold',
                        compact ? 'size-5 text-[10px]' : 'size-7 text-[11px]',
                        stop.kind === 'origin'
                            ? 'border-teal-500/40 bg-teal-500/10 text-teal-700 dark:text-teal-300'
                            : stop.kind === 'return'
                              ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                              : 'border-border bg-muted text-muted-foreground',
                    )
                "
            >
                {{ stop.sequence }}
            </span>

            <div class="min-w-0">
                <div
                    class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5"
                >
                    <p
                        :class="
                            cn(
                                'leading-tight break-words',
                                compact ? 'font-medium' : 'font-medium',
                            )
                        "
                    >
                        {{ stop.label ?? 'Unnamed stop' }}
                    </p>
                    <Badge
                        variant="outline"
                        class="shrink-0 px-1 py-0 text-[9px] uppercase"
                    >
                        {{ stopKindLabel(stop) }}
                    </Badge>
                </div>
            </div>

            <p
                :class="
                    cn(
                        'text-center text-muted-foreground',
                        compact ? 'text-[11px]' : 'text-sm',
                    )
                "
            >
                <span v-if="nightsLabel(stop.nights)">
                    {{ nightsLabel(stop.nights) }}
                </span>
                <span v-else class="text-border">—</span>
            </p>

            <div
                :class="
                    cn(
                        'space-y-0.5 text-right leading-snug text-muted-foreground',
                        compact ? 'text-[10px]' : 'text-xs sm:text-sm',
                    )
                "
            >
                <p v-if="stop.kind === 'origin' && departureLabel(stop)">
                    {{ departureLabel(stop) }}
                </p>
                <template v-else-if="stop.kind === 'stay'">
                    <p v-if="arrivalLabel(stop)">{{ arrivalLabel(stop) }}</p>
                    <p v-if="departureLabel(stop)">
                        {{ departureLabel(stop) }}
                    </p>
                </template>
                <p v-else-if="stop.kind === 'return' && arrivalLabel(stop)">
                    {{ arrivalLabel(stop) }}
                </p>
            </div>
        </li>
    </ol>
</template>
