<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    CalendarDays,
    Compass,
    Map,
    MapPin,
    MessageCircle,
    Plane,
    Plus,
    Route,
    Sparkles,
    Star,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatDisplayDateRange, formatRelativeTime } from '@/lib/dates';
import { dashboard } from '@/routes';
import { index as assistantIndex } from '@/routes/assistant';
import { index as openTripsIndex } from '@/routes/open-trips';
import {
    create as roadTripCreate,
    index as roadTripsIndex,
} from '@/routes/road-trips';
import { create, index as tripsIndex, show } from '@/routes/trips';
import type { Trip, TripPhase, TripPhaseCounts } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const props = defineProps<{
    stats: {
        trips: number;
        road_trips: number;
        favorites: number;
        upcoming: string | null;
        invited: number;
    };
    phaseCounts: TripPhaseCounts;
    nextTrip: Trip | null;
    recentTrips: Trip[];
    invitedTrips: Trip[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const firstName = page.props.auth.user?.name?.split(' ')[0] ?? 'Traveler';

const now = new Date();
const todayLabel = now.toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});
const greeting =
    now.getHours() < 12
        ? 'Good morning'
        : now.getHours() < 18
          ? 'Good afternoon'
          : 'Good evening';

const MS_PER_DAY = 86_400_000;

function daysFromToday(iso: string): number {
    const [year, month, day] = iso.split('-').map(Number);
    const target = new Date(year, month - 1, day).getTime();
    const today = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
    ).getTime();

    return Math.round((target - today) / MS_PER_DAY);
}

const nextTripTiming = computed(() => {
    const trip = props.nextTrip;

    if (!trip?.start_date) {
        return null;
    }

    const untilStart = daysFromToday(trip.start_date);

    if (untilStart > 0) {
        return {
            value: untilStart,
            unit: untilStart === 1 ? 'day to go' : 'days to go',
            label: 'Upcoming',
            progress: null,
        };
    }

    if (untilStart === 0) {
        return {
            value: 'Today',
            unit: 'departure',
            label: 'Departing',
            progress: null,
        };
    }

    const totalDays = trip.end_date
        ? daysFromToday(trip.end_date) - untilStart + 1
        : 1;
    const currentDay = Math.min(-untilStart + 1, totalDays);

    return {
        value: `Day ${currentDay}`,
        unit: `of ${totalDays}`,
        label: 'In progress',
        progress: Math.round((currentDay / totalDays) * 100),
    };
});

const kpis = computed(() => [
    {
        label: 'Active trips',
        value: props.stats.trips,
        detail: `${props.phaseCounts.upcoming} upcoming`,
        icon: Map,
        tile: 'from-teal-500 to-emerald-500 shadow-teal-500/25',
    },
    {
        label: 'Road trips',
        value: props.stats.road_trips,
        detail: 'Routes & stops mapped',
        icon: Route,
        tile: 'from-sky-500 to-indigo-500 shadow-sky-500/25',
    },
    {
        label: 'Favourites',
        value: props.stats.favorites,
        detail: 'Starred itineraries',
        icon: Star,
        tile: 'from-amber-400 to-orange-500 shadow-amber-500/25',
    },
    {
        label: 'Shared with you',
        value: props.stats.invited,
        detail: 'Collaborative trips',
        icon: Users,
        tile: 'from-violet-500 to-fuchsia-500 shadow-violet-500/25',
    },
]);

const phaseTotal = computed(
    () =>
        props.phaseCounts.upcoming +
        props.phaseCounts.ongoing +
        props.phaseCounts.past,
);

const phaseRows = computed(() => [
    {
        key: 'ongoing',
        label: 'In progress',
        count: props.phaseCounts.ongoing,
        bar: 'bg-linear-to-r from-teal-400 to-emerald-500',
    },
    {
        key: 'upcoming',
        label: 'Upcoming',
        count: props.phaseCounts.upcoming,
        bar: 'bg-linear-to-r from-sky-400 to-indigo-500',
    },
    {
        key: 'past',
        label: 'Completed',
        count: props.phaseCounts.past,
        bar: 'bg-linear-to-r from-violet-400 to-fuchsia-500',
    },
]);

function phaseShare(count: number): string {
    return phaseTotal.value === 0
        ? '0%'
        : `${Math.round((count / phaseTotal.value) * 100)}%`;
}

/** The status column shows where each trip is in time. */
const phaseDot: Record<TripPhase, string> = {
    upcoming: 'bg-sky-500 ring-sky-500/20',
    ongoing: 'bg-emerald-500 ring-emerald-500/20',
    past: 'bg-slate-400 ring-slate-400/20',
};

const shortcuts = [
    {
        title: 'Plan a vacation',
        description: 'Build a new itinerary',
        href: create(),
        icon: Plane,
        chip: 'from-sky-500 to-blue-500 shadow-sky-500/30',
    },
    {
        title: 'Map a road trip',
        description: 'Routes, stops & breaks',
        href: roadTripCreate(),
        icon: Route,
        chip: 'from-amber-400 to-orange-500 shadow-amber-500/30',
    },
    {
        title: 'Travel assistant',
        description: 'Ask anything about travel',
        href: assistantIndex(),
        icon: MessageCircle,
        chip: 'from-violet-500 to-fuchsia-500 shadow-violet-500/30',
    },
    {
        title: 'Discover group trips',
        description: 'Join an open trip',
        href: openTripsIndex(),
        icon: Compass,
        chip: 'from-teal-500 to-emerald-500 shadow-teal-500/30',
    },
];

function initials(name: string | null | undefined): string {
    return (name ?? '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 p-4 md:p-8">
        <!-- Header -->
        <header
            class="brand-gradient relative isolate flex flex-col gap-5 overflow-hidden rounded-3xl p-6 text-white shadow-xl shadow-cyan-500/20 md:flex-row md:items-end md:justify-between md:p-8"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-20 -right-10 -z-10 size-72 rounded-full bg-fuchsia-400/35 blur-3xl"
            />
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-24 left-10 -z-10 size-64 rounded-full bg-amber-300/30 blur-3xl"
            />
            <div
                aria-hidden="true"
                class="pointer-events-none absolute top-4 left-1/2 -z-10 size-28 rounded-full bg-white/15 blur-2xl"
            />
            <div>
                <p
                    class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold tracking-[0.14em] uppercase backdrop-blur-sm"
                >
                    <Sparkles class="size-3.5" />
                    {{ todayLabel }}
                </p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight md:text-4xl">
                    {{ greeting }}, {{ firstName }}
                </h1>
                <p class="mt-1.5 text-sm text-white/85 md:text-base">
                    Here's an overview of your travel portfolio.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    class="border-white/40 bg-white/15 text-white backdrop-blur-sm hover:bg-white/25 hover:text-white dark:border-white/30 dark:bg-white/10 dark:hover:bg-white/20"
                    as-child
                >
                    <Link :href="roadTripsIndex()">
                        <Route class="size-4" />
                        Road trips
                    </Link>
                </Button>
                <Button
                    class="bg-white bg-none! text-teal-700 shadow-lg hover:bg-white/90 hover:brightness-100"
                    as-child
                >
                    <Link :href="create()">
                        <Plus class="size-4" />
                        New trip
                    </Link>
                </Button>
            </div>
        </header>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="group relative isolate flex flex-col gap-3 overflow-hidden rounded-2xl bg-linear-to-br p-5 text-white shadow-lg transition-transform duration-200 hover:-translate-y-1 md:p-6"
                :class="kpi.tile"
            >
                <component
                    :is="kpi.icon"
                    aria-hidden="true"
                    class="pointer-events-none absolute -right-4 -bottom-4 -z-10 size-24 text-white/15 transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6"
                />
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-white/90">
                        {{ kpi.label }}
                    </span>
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm"
                    >
                        <component :is="kpi.icon" class="size-4" />
                    </span>
                </div>
                <span class="text-4xl font-bold tracking-tight tabular-nums">
                    {{ kpi.value }}
                </span>
                <span class="text-xs text-white/80">
                    {{ kpi.detail }}
                </span>
            </div>
        </section>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="flex flex-col gap-8 lg:col-span-2">
                <!-- Next departure -->
                <section>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="section-heading">Next departure</h2>
                    </div>

                    <Link
                        v-if="nextTrip"
                        :href="show(nextTrip.id)"
                        class="group grid overflow-hidden rounded-2xl border border-sky-200/70 bg-card shadow-lg shadow-sky-500/10 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-sky-500/20 sm:grid-cols-5 dark:border-sky-500/20"
                    >
                        <div
                            class="relative h-44 bg-muted sm:col-span-2 sm:h-auto"
                        >
                            <img
                                v-if="
                                    nextTrip.cover_image_thumb_url ??
                                    nextTrip.cover_image_url
                                "
                                :src="
                                    (nextTrip.cover_image_thumb_url ??
                                        nextTrip.cover_image_url)!
                                "
                                :alt="nextTrip.title"
                                class="absolute inset-0 size-full object-cover"
                            />
                            <div
                                v-else
                                class="absolute inset-0 flex items-center justify-center bg-linear-to-br from-sky-500 via-indigo-500 to-violet-600"
                            >
                                <MapPin class="size-10 text-white/60" />
                            </div>
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/50 via-black/0 to-black/0"
                            />
                            <span
                                v-if="nextTripTiming"
                                class="absolute top-3 left-3 rounded-full bg-linear-to-r from-amber-400 to-orange-500 px-2.5 py-1 text-[11px] font-bold tracking-wide text-white uppercase shadow-md"
                            >
                                {{ nextTripTiming.label }}
                            </span>
                        </div>

                        <div
                            class="flex flex-col justify-between gap-6 p-5 sm:col-span-3 md:p-6"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-semibold tracking-wide text-sky-600 uppercase dark:text-sky-300"
                                >
                                    {{ nextTrip.type_label }}
                                    <template
                                        v-if="nextTrip.travel_style_label"
                                    >
                                        · {{ nextTrip.travel_style_label }}
                                    </template>
                                </p>
                                <h3
                                    class="mt-1.5 truncate text-xl font-semibold tracking-tight"
                                >
                                    {{ nextTrip.title }}
                                </h3>
                                <dl
                                    class="mt-4 grid grid-cols-1 gap-2.5 text-sm text-muted-foreground"
                                >
                                    <div class="flex items-center gap-2">
                                        <MapPin
                                            class="size-4 shrink-0 text-rose-500"
                                        />
                                        <dd class="truncate">
                                            {{
                                                nextTrip.route_summary
                                                    ?.route_label ||
                                                locationLabel(
                                                    nextTrip.destination,
                                                ) ||
                                                'Destination not set'
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <CalendarDays
                                            class="size-4 shrink-0 text-sky-500"
                                        />
                                        <dd>
                                            {{
                                                formatDisplayDateRange(
                                                    nextTrip.start_date,
                                                    nextTrip.end_date,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Users
                                            class="size-4 shrink-0 text-violet-500"
                                        />
                                        <dd>
                                            {{ nextTrip.travelers }}
                                            {{
                                                nextTrip.travelers === 1
                                                    ? 'traveller'
                                                    : 'travellers'
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div
                                class="flex items-end justify-between gap-4 border-t border-border pt-4"
                            >
                                <div v-if="nextTripTiming" class="flex-1">
                                    <p class="flex items-baseline gap-1.5">
                                        <span
                                            class="brand-gradient-text text-3xl font-bold tracking-tight tabular-nums"
                                        >
                                            {{ nextTripTiming.value }}
                                        </span>
                                        <span
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ nextTripTiming.unit }}
                                        </span>
                                    </p>
                                    <div
                                        v-if="nextTripTiming.progress !== null"
                                        class="mt-2 h-1.5 w-full max-w-48 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="brand-gradient h-full rounded-full"
                                            :style="{
                                                width: `${nextTripTiming.progress}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1 text-sm font-semibold text-sky-700 transition-colors group-hover:bg-sky-500 group-hover:text-white dark:bg-sky-500/15 dark:text-sky-300"
                                >
                                    Open
                                    <ArrowRight
                                        class="size-4 transition-transform group-hover:translate-x-0.5"
                                    />
                                </span>
                            </div>
                        </div>
                    </Link>

                    <div
                        v-else
                        class="flex flex-col items-start gap-4 rounded-2xl border-2 border-dashed border-sky-300/70 bg-linear-to-br from-sky-50 to-violet-50 p-6 sm:flex-row sm:items-center sm:justify-between dark:border-sky-500/30 dark:from-sky-500/10 dark:to-violet-500/10"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-11 items-center justify-center rounded-xl bg-linear-to-br from-sky-500 to-violet-500 text-white shadow-md shadow-sky-500/30"
                            >
                                <CalendarDays class="size-5" />
                            </div>
                            <div>
                                <p class="font-medium">
                                    No departures scheduled
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Add dates to a trip to see it here.
                                </p>
                            </div>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="create()">Plan a trip</Link>
                        </Button>
                    </div>
                </section>

                <!-- Recent trips -->
                <section>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="section-heading">Recent activity</h2>
                        <Link
                            :href="tripsIndex()"
                            class="inline-flex items-center gap-1 rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-700 transition-colors hover:bg-teal-500 hover:text-white dark:bg-teal-500/15 dark:text-teal-300"
                        >
                            View all
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </div>

                    <div
                        class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-lg shadow-violet-500/5"
                    >
                        <div
                            class="hidden grid-cols-12 gap-4 bg-linear-to-r from-teal-50 via-sky-50 to-violet-50 px-5 py-2.5 text-xs font-semibold tracking-wide text-slate-600 uppercase md:grid dark:from-teal-500/10 dark:via-sky-500/10 dark:to-violet-500/10 dark:text-slate-300"
                        >
                            <span class="col-span-5">Trip</span>
                            <span class="col-span-3">Dates</span>
                            <span class="col-span-2">Status</span>
                            <span class="col-span-2 text-right">Updated</span>
                        </div>

                        <Link
                            v-for="trip in recentTrips"
                            :key="trip.id"
                            :href="show(trip.id)"
                            class="grid grid-cols-1 gap-1 border-b border-border/60 px-5 py-3.5 transition-colors last:border-b-0 hover:bg-sky-50/70 md:grid-cols-12 md:items-center md:gap-4 dark:hover:bg-sky-500/5"
                        >
                            <div
                                class="flex min-w-0 items-center gap-3 md:col-span-5"
                            >
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-linear-to-br text-white shadow-md"
                                    :class="
                                        trip.type === 'road'
                                            ? 'from-amber-400 to-orange-500 shadow-amber-500/25'
                                            : 'from-sky-500 to-indigo-500 shadow-sky-500/25'
                                    "
                                >
                                    <Route
                                        v-if="trip.type === 'road'"
                                        class="size-4"
                                    />
                                    <Plane v-else class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ trip.title }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{
                                            locationLabel(trip.destination) ??
                                            'No destination'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="pl-12 text-xs text-muted-foreground tabular-nums md:col-span-3 md:pl-0 md:text-sm"
                            >
                                {{
                                    formatDisplayDateRange(
                                        trip.start_date,
                                        trip.end_date,
                                    )
                                }}
                            </span>
                            <span
                                class="hidden min-w-0 flex-col md:col-span-2 md:flex"
                            >
                                <span
                                    class="flex items-center gap-2 text-sm font-medium"
                                >
                                    <span
                                        class="size-2 shrink-0 rounded-full ring-4"
                                        :class="phaseDot[trip.phase]"
                                    />
                                    {{ trip.phase_label }}
                                </span>
                                <span
                                    v-if="
                                        trip.status === 'draft' &&
                                        trip.phase !== 'past'
                                    "
                                    class="pl-4 text-xs text-amber-600 dark:text-amber-400"
                                >
                                    No itinerary yet
                                </span>
                            </span>
                            <span
                                class="hidden text-right text-sm text-muted-foreground md:col-span-2 md:block"
                            >
                                {{ formatRelativeTime(trip.updated_at) }}
                            </span>
                        </Link>

                        <div
                            v-if="recentTrips.length === 0"
                            class="px-5 py-10 text-center"
                        >
                            <p class="text-sm font-medium">No trips yet</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Your trips will appear here once you create one.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="flex flex-col gap-8">
                <!-- Portfolio breakdown -->
                <section>
                    <h2 class="section-heading mb-3">Trip portfolio</h2>
                    <div
                        class="rounded-2xl border border-border/60 bg-card p-5 shadow-lg shadow-teal-500/5"
                    >
                        <div class="flex items-baseline justify-between">
                            <span
                                class="brand-gradient-text text-3xl font-bold tracking-tight tabular-nums"
                            >
                                {{ phaseTotal }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                active trips
                            </span>
                        </div>
                        <div
                            class="mt-4 flex h-3 w-full gap-1 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                v-for="row in phaseRows"
                                v-show="row.count > 0"
                                :key="row.key"
                                :class="row.bar"
                                :style="{ width: phaseShare(row.count) }"
                            />
                        </div>
                        <ul class="mt-5 space-y-3">
                            <li
                                v-for="row in phaseRows"
                                :key="row.key"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="flex items-center gap-2.5">
                                    <span
                                        class="size-3 rounded-full"
                                        :class="row.bar"
                                    />
                                    {{ row.label }}
                                </span>
                                <span class="flex items-center gap-3">
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{ phaseShare(row.count) }}
                                    </span>
                                    <span
                                        class="w-6 text-right font-medium tabular-nums"
                                    >
                                        {{ row.count }}
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Shared with you -->
                <section v-if="invitedTrips.length > 0">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="section-heading">Shared with you</h2>
                        <Link
                            :href="tripsIndex({ query: { filter: 'shared' } })"
                            class="inline-flex items-center gap-1 rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-700 transition-colors hover:bg-teal-500 hover:text-white dark:bg-teal-500/15 dark:text-teal-300"
                        >
                            View all
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </div>
                    <ul
                        class="divide-y divide-border/60 overflow-hidden rounded-2xl border border-border/60 bg-card shadow-lg shadow-sky-500/5"
                    >
                        <li v-for="trip in invitedTrips" :key="trip.id">
                            <Link
                                :href="show(trip.id)"
                                class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-violet-50/70 dark:hover:bg-violet-500/5"
                            >
                                <span
                                    class="brand-gradient flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white shadow-sm"
                                >
                                    {{ initials(trip.owner?.name) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-medium"
                                    >
                                        {{ trip.title }}
                                    </span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                    >
                                        {{ trip.owner?.name ?? 'Unknown' }}
                                    </span>
                                </span>
                                <span
                                    class="rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-semibold text-violet-700 capitalize dark:bg-violet-500/15 dark:text-violet-300"
                                >
                                    {{ trip.collaborator_role }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <!-- Shortcuts -->
                <section>
                    <h2 class="section-heading mb-3">Shortcuts</h2>
                    <ul
                        class="divide-y divide-border/60 overflow-hidden rounded-2xl border border-border/60 bg-card shadow-lg shadow-sky-500/5"
                    >
                        <li v-for="shortcut in shortcuts" :key="shortcut.title">
                            <Link
                                :href="shortcut.href"
                                class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-sky-50/70 dark:hover:bg-sky-500/5"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-linear-to-br text-white shadow-md transition-transform group-hover:scale-110"
                                    :class="shortcut.chip"
                                >
                                    <component
                                        :is="shortcut.icon"
                                        class="size-4"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium">
                                        {{ shortcut.title }}
                                    </span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{ shortcut.description }}
                                    </span>
                                </span>
                                <ArrowRight
                                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                />
                            </Link>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</template>
