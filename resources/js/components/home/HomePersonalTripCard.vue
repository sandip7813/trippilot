<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Globe2, MapPin, Plane, Route, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import { tripCardAccent } from '@/lib/card-accents';
import { daysFromToday, formatDisplayDateRange } from '@/lib/dates';
import { show as roadTripShow } from '@/routes/road-trips';
import { show as tripShow } from '@/routes/trips';
import type { HomePersonalTrip } from '@/types/home';

const props = withDefaults(
    defineProps<{
        trip: HomePersonalTrip;
        index?: number;
    }>(),
    { index: 0 },
);

const href = computed(() =>
    props.trip.type === 'road'
        ? roadTripShow(props.trip.id)
        : tripShow(props.trip.id),
);

const timing = computed(() => {
    const startsIn = daysFromToday(props.trip.start_date);
    const endsIn = daysFromToday(props.trip.end_date ?? props.trip.start_date);

    if (startsIn === null) {
        return { label: 'Dates not set', tone: 'bg-slate-500/80' };
    }

    if (endsIn !== null && endsIn < 0) {
        const ago = Math.abs(endsIn);

        return {
            label:
                ago < 7
                    ? `Ended ${ago === 1 ? 'yesterday' : `${ago} days ago`}`
                    : ago < 60
                      ? `${Math.round(ago / 7)} week${Math.round(ago / 7) === 1 ? '' : 's'} ago`
                      : `${Math.round(ago / 30)} months ago`,
            tone: 'bg-slate-700/80',
        };
    }

    if (startsIn <= 0) {
        return { label: 'Happening now', tone: 'bg-emerald-500' };
    }

    return {
        label: startsIn === 1 ? 'Tomorrow' : `In ${startsIn} days`,
        tone: startsIn <= 7 ? 'bg-orange-500' : 'bg-teal-600',
    };
});
</script>

<template>
    <Link
        :href="href"
        class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-2xl shadow-md transition duration-300 hover:-translate-y-1 hover:shadow-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:aspect-[3/4]"
    >
        <img
            v-if="trip.cover_image_thumb_url"
            :src="trip.cover_image_thumb_url"
            :alt="trip.title"
            loading="lazy"
            class="absolute inset-0 size-full object-cover transition-transform duration-700 group-hover:scale-105"
        />
        <div
            v-else
            class="absolute inset-0 bg-gradient-to-br"
            :class="tripCardAccent(index)"
        />
        <div
            class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-black/5"
        />

        <div
            class="absolute inset-x-3 top-3 flex items-start justify-between gap-2"
        >
            <span
                class="rounded-full px-2.5 py-0.5 text-xs font-semibold text-white shadow-sm backdrop-blur"
                :class="timing.tone"
            >
                {{ timing.label }}
            </span>
            <span
                class="flex size-7 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur"
                :title="trip.type_label"
            >
                <Route v-if="trip.type === 'road'" class="size-3.5" />
                <Plane v-else class="size-3.5" />
            </span>
        </div>

        <div class="relative space-y-1.5 p-4 text-white">
            <h3 class="line-clamp-2 leading-snug font-semibold">
                {{ trip.title }}
            </h3>
            <p
                v-if="trip.destination"
                class="flex items-center gap-1 text-xs text-white/80"
            >
                <MapPin class="size-3 shrink-0" />
                <span class="truncate">{{ trip.destination }}</span>
            </p>
            <div
                class="flex items-center justify-between gap-2 pt-1 text-[11px] text-white/70"
            >
                <span class="tabular-nums">
                    {{ formatDisplayDateRange(trip.start_date, trip.end_date) }}
                </span>
                <span
                    v-if="!trip.is_owner"
                    class="flex items-center gap-1"
                    title="Shared with you"
                >
                    <UserRound class="size-3" /> Joined
                </span>
                <span
                    v-else-if="trip.is_public"
                    class="flex items-center gap-1"
                    title="Open trip"
                >
                    <Globe2 class="size-3" /> Open
                </span>
            </div>
        </div>
    </Link>
</template>
