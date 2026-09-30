<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Compass, MapPin, Users, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import { tripCardAccent } from '@/lib/card-accents';
import { daysFromToday, formatShortDate } from '@/lib/dates';
import { show as openTripShow } from '@/routes/open-trips';
import type { HomeOpenTrip } from '@/types/home';
import { openTripCategoryLabel } from '@/types/home';

const props = withDefaults(
    defineProps<{
        trip: HomeOpenTrip;
        index?: number;
        compact?: boolean;
    }>(),
    { index: 0, compact: false },
);

const categoryLabel = computed(() =>
    openTripCategoryLabel(props.trip.group.category),
);

const departureLabel = computed(() => {
    if (props.trip.is_past) {
        return 'Completed';
    }

    const days = daysFromToday(props.trip.start_date);

    if (days === null) {
        return 'Dates TBA';
    }

    if (days <= 0) {
        return 'Happening now';
    }

    if (days === 1) {
        return 'Leaves tomorrow';
    }

    return days <= 30 ? `Leaves in ${days} days` : 'Departing soon';
});

const seatStatus = computed(() => {
    const { seats_left: seatsLeft, max_group_size: maxSize } = props.trip.group;

    if (props.trip.is_past || seatsLeft === null || maxSize === null) {
        return null;
    }

    const filledPercent = Math.round(((maxSize - seatsLeft) / maxSize) * 100);

    return {
        seatsLeft,
        maxSize,
        filledPercent,
        label:
            seatsLeft === 0
                ? 'Group full'
                : seatsLeft <= 2
                  ? `Only ${seatsLeft} seat${seatsLeft === 1 ? '' : 's'} left`
                  : `${seatsLeft} of ${maxSize} seats open`,
        tone:
            seatsLeft === 0
                ? 'bg-muted-foreground/50'
                : seatsLeft <= 2
                  ? 'bg-amber-500'
                  : 'bg-teal-500',
    };
});

const costLabel = computed(() => {
    const { cost_amount: amount, cost_currency: currency } = props.trip.group;

    if (amount) {
        return `${currency ?? ''} ${Number(amount).toLocaleString()}`.trim();
    }

    return props.trip.group.cost_model_label;
});
</script>

<template>
    <Link
        :href="openTripShow(trip.id)"
        class="group flex h-full flex-col overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-teal-900/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
    >
        <div
            class="relative overflow-hidden"
            :class="compact ? 'h-32' : 'h-44'"
        >
            <img
                v-if="trip.cover_image_thumb_url"
                :src="trip.cover_image_thumb_url"
                :alt="trip.title"
                loading="lazy"
                class="size-full object-cover transition-transform duration-700 group-hover:scale-105"
                :class="{ 'grayscale-[35%]': trip.is_past }"
            />
            <div
                v-else
                class="flex size-full items-center justify-center bg-gradient-to-br"
                :class="tripCardAccent(index)"
            >
                <Compass class="size-10 text-white/70" />
            </div>
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/10"
            />

            <span
                v-if="categoryLabel"
                class="absolute top-3 left-3 rounded-full bg-white/90 px-2.5 py-0.5 text-xs font-semibold text-slate-800 shadow-sm backdrop-blur"
            >
                {{ categoryLabel }}
            </span>

            <div
                v-if="trip.start_date"
                class="absolute top-3 right-3 flex flex-col items-center rounded-xl bg-white/95 px-2.5 py-1 text-center leading-none shadow-sm"
            >
                <span class="text-sm font-bold text-slate-900 tabular-nums">
                    {{ formatShortDate(trip.start_date).split(' ')[0] }}
                </span>
                <span
                    class="mt-0.5 text-[10px] font-semibold tracking-wide text-teal-700 uppercase"
                >
                    {{ formatShortDate(trip.start_date).split(' ')[1] }}
                </span>
            </div>

            <span
                class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 text-xs font-medium text-white"
            >
                <span
                    class="size-1.5 rounded-full"
                    :class="
                        trip.is_past
                            ? 'bg-white/60'
                            : 'animate-pulse bg-emerald-400'
                    "
                />
                {{ departureLabel }}
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="space-y-1">
                <h3
                    class="line-clamp-1 font-semibold tracking-tight group-hover:text-primary"
                >
                    {{ trip.title }}
                </h3>
                <p
                    v-if="trip.destination"
                    class="flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <MapPin class="size-3.5 shrink-0" />
                    <span class="truncate">{{ trip.destination.label }}</span>
                </p>
            </div>

            <div v-if="seatStatus && !compact" class="space-y-1.5">
                <div
                    class="flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1">
                        <Users class="size-3.5" />
                        {{ seatStatus.label }}
                    </span>
                    <span class="tabular-nums"
                        >{{ seatStatus.filledPercent }}%</span
                    >
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="seatStatus.tone"
                        :style="{
                            width: `${Math.max(seatStatus.filledPercent, 4)}%`,
                        }"
                    />
                </div>
            </div>

            <div
                class="mt-auto flex items-center justify-between gap-2 border-t border-border/60 pt-3 text-xs text-muted-foreground"
            >
                <span
                    v-if="trip.organizer"
                    class="flex min-w-0 items-center gap-2"
                >
                    <span
                        class="flex size-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white uppercase"
                        :class="tripCardAccent(index + 2)"
                    >
                        {{ trip.organizer.name.charAt(0) }}
                    </span>
                    <span class="truncate">{{ trip.organizer.name }}</span>
                </span>
                <span
                    v-if="compact && trip.end_date"
                    class="flex items-center gap-1"
                >
                    <CalendarDays class="size-3.5" />
                    {{ formatShortDate(trip.end_date) }}
                </span>
                <span
                    v-else-if="costLabel"
                    class="flex items-center gap-1 font-medium text-foreground"
                >
                    <Wallet class="size-3.5 text-muted-foreground" />
                    {{ costLabel }}
                    <span
                        v-if="trip.group.cost_amount"
                        class="font-normal text-muted-foreground"
                        >/ person</span
                    >
                </span>
            </div>
        </div>
    </Link>
</template>
