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
import type { Trip, TripPhaseCounts, TripStatus } from '@/types/trip';
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
    },
    {
        label: 'Road trips',
        value: props.stats.road_trips,
        detail: 'Routes & stops mapped',
        icon: Route,
    },
    {
        label: 'Favourites',
        value: props.stats.favorites,
        detail: 'Starred itineraries',
        icon: Star,
    },
    {
        label: 'Shared with you',
        value: props.stats.invited,
        detail: 'Collaborative trips',
        icon: Users,
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
        bar: 'bg-primary',
    },
    {
        key: 'upcoming',
        label: 'Upcoming',
        count: props.phaseCounts.upcoming,
        bar: 'bg-sky-500',
    },
    {
        key: 'past',
        label: 'Completed',
        count: props.phaseCounts.past,
        bar: 'bg-slate-400 dark:bg-slate-500',
    },
]);

function phaseShare(count: number): string {
    return phaseTotal.value === 0
        ? '0%'
        : `${Math.round((count / phaseTotal.value) * 100)}%`;
}

const statusDot: Record<TripStatus, string> = {
    draft: 'bg-amber-500',
    planned: 'bg-emerald-500',
    archived: 'bg-slate-400',
};

const shortcuts = [
    {
        title: 'Plan a vacation',
        description: 'Build a new itinerary',
        href: create(),
        icon: Plane,
    },
    {
        title: 'Map a road trip',
        description: 'Routes, stops & breaks',
        href: roadTripCreate(),
        icon: Route,
    },
    {
        title: 'Travel assistant',
        description: 'Ask anything about travel',
        href: assistantIndex(),
        icon: MessageCircle,
    },
    {
        title: 'Discover group trips',
        description: 'Join an open trip',
        href: openTripsIndex(),
        icon: Compass,
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
            class="flex flex-col gap-5 border-b border-border pb-6 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <p
                    class="text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase"
                >
                    {{ todayLabel }}
                </p>
                <h1
                    class="mt-2 text-3xl font-semibold tracking-tight text-foreground"
                >
                    {{ greeting }}, {{ firstName }}
                </h1>
                <p class="mt-1.5 text-sm text-muted-foreground">
                    Here's an overview of your travel portfolio.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="roadTripsIndex()">
                        <Route class="size-4" />
                        Road trips
                    </Link>
                </Button>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="size-4" />
                        New trip
                    </Link>
                </Button>
            </div>
        </header>

        <!-- KPI strip -->
        <section
            class="grid grid-cols-2 overflow-hidden rounded-xl border border-border bg-card lg:grid-cols-4"
        >
            <div
                v-for="(kpi, index) in kpis"
                :key="kpi.label"
                class="flex flex-col gap-3 p-5 md:p-6"
                :class="[
                    index % 2 === 1 ? 'border-l border-border' : '',
                    index >= 2 ? 'border-t border-border lg:border-t-0' : '',
                    index === 2 ? 'lg:border-l' : '',
                ]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">
                        {{ kpi.label }}
                    </span>
                    <component
                        :is="kpi.icon"
                        class="size-4 text-muted-foreground/70"
                    />
                </div>
                <span
                    class="text-3xl font-semibold tracking-tight tabular-nums"
                >
                    {{ kpi.value }}
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ kpi.detail }}
                </span>
            </div>
        </section>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="flex flex-col gap-8 lg:col-span-2">
                <!-- Next departure -->
                <section>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold tracking-tight">
                            Next departure
                        </h2>
                    </div>

                    <Link
                        v-if="nextTrip"
                        :href="show(nextTrip.id)"
                        class="group grid overflow-hidden rounded-xl border border-border bg-card transition-colors hover:border-foreground/20 sm:grid-cols-5"
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
                                class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-slate-800 to-slate-900"
                            >
                                <MapPin class="size-8 text-white/40" />
                            </div>
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/50 via-black/0 to-black/0"
                            />
                            <span
                                v-if="nextTripTiming"
                                class="absolute top-3 left-3 rounded-md bg-white/95 px-2 py-1 text-[11px] font-semibold tracking-wide text-slate-900 uppercase shadow-sm"
                            >
                                {{ nextTripTiming.label }}
                            </span>
                        </div>

                        <div
                            class="flex flex-col justify-between gap-6 p-5 sm:col-span-3 md:p-6"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
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
                                        <MapPin class="size-4 shrink-0" />
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
                                        <CalendarDays class="size-4 shrink-0" />
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
                                        <Users class="size-4 shrink-0" />
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
                                            class="text-2xl font-semibold tracking-tight tabular-nums"
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
                                            class="h-full rounded-full bg-primary"
                                            :style="{
                                                width: `${nextTripTiming.progress}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center gap-1 text-sm font-medium text-foreground"
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
                        class="flex flex-col items-start gap-4 rounded-xl border border-dashed border-border bg-card p-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-10 items-center justify-center rounded-lg bg-muted"
                            >
                                <CalendarDays
                                    class="size-5 text-muted-foreground"
                                />
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
                        <h2 class="text-sm font-semibold tracking-tight">
                            Recent activity
                        </h2>
                        <Link
                            :href="tripsIndex()"
                            class="inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            View all
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-border bg-card"
                    >
                        <div
                            class="hidden grid-cols-12 gap-4 border-b border-border bg-muted/40 px-5 py-2.5 text-xs font-medium tracking-wide text-muted-foreground uppercase md:grid"
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
                            class="grid grid-cols-1 gap-1 border-b border-border px-5 py-3.5 transition-colors last:border-b-0 hover:bg-muted/40 md:grid-cols-12 md:items-center md:gap-4"
                        >
                            <div
                                class="flex min-w-0 items-center gap-3 md:col-span-5"
                            >
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/50 text-muted-foreground"
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
                                class="hidden items-center gap-2 text-sm md:col-span-2 md:flex"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="statusDot[trip.status]"
                                />
                                {{ trip.status_label }}
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
                    <h2 class="mb-3 text-sm font-semibold tracking-tight">
                        Trip portfolio
                    </h2>
                    <div class="rounded-xl border border-border bg-card p-5">
                        <div class="flex items-baseline justify-between">
                            <span
                                class="text-2xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ phaseTotal }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                active trips
                            </span>
                        </div>
                        <div
                            class="mt-4 flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-muted"
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
                                        class="size-2 rounded-sm"
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
                        <h2 class="text-sm font-semibold tracking-tight">
                            Shared with you
                        </h2>
                        <Link
                            :href="tripsIndex({ query: { filter: 'shared' } })"
                            class="inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            View all
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </div>
                    <ul
                        class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
                    >
                        <li v-for="trip in invitedTrips" :key="trip.id">
                            <Link
                                :href="show(trip.id)"
                                class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
                            >
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground"
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
                                    class="rounded-md border border-border px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground capitalize"
                                >
                                    {{ trip.collaborator_role }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <!-- Shortcuts -->
                <section>
                    <h2 class="mb-3 text-sm font-semibold tracking-tight">
                        Shortcuts
                    </h2>
                    <ul
                        class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
                    >
                        <li v-for="shortcut in shortcuts" :key="shortcut.title">
                            <Link
                                :href="shortcut.href"
                                class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
                            >
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
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
