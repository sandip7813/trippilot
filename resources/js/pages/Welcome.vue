<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    Bot,
    CalendarClock,
    CloudSun,
    Compass,
    Globe2,
    History,
    Map,
    MapPin,
    Plus,
    Route,
    Sparkles,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import HomeOpenTripCard from '@/components/home/HomeOpenTripCard.vue';
import HomePersonalTripCard from '@/components/home/HomePersonalTripCard.vue';
import HomeSearch from '@/components/home/HomeSearch.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Button } from '@/components/ui/button';
import { featureIconAccent } from '@/lib/card-accents';
import { daysFromToday, formatDisplayDateRange } from '@/lib/dates';
import { register } from '@/routes';
import { index as openTripsIndex } from '@/routes/open-trips';
import { create as createRoadTrip } from '@/routes/road-trips';
import { create as createTrip, index as tripsIndex } from '@/routes/trips';
import type {
    HomeCategory,
    HomeDestination,
    HomeMyTrips,
    HomeOpenTrip,
    HomeStats,
} from '@/types/home';
import { openTripCategoryLabel } from '@/types/home';

const props = defineProps<{
    stats: HomeStats;
    openTrips: HomeOpenTrip[];
    pastOpenTrips: HomeOpenTrip[];
    destinations: HomeDestination[];
    categories: HomeCategory[];
    myTrips: HomeMyTrips | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const greeting = computed(() => {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 18
          ? 'Good afternoon'
          : 'Good evening';
});

/** Lifts the hero above the stats bar while search suggestions are showing. */
const isSearchOpen = ref(false);

const journeyTab = ref<'upcoming' | 'past'>('upcoming');

const journeyTrips = computed(() =>
    props.myTrips
        ? journeyTab.value === 'upcoming'
            ? props.myTrips.upcoming
            : props.myTrips.past
        : [],
);

/** The trip under way, or the soonest departure, shown in the hero. */
const nextTrip = computed(() => props.myTrips?.upcoming[0] ?? null);

const nextTripCountdown = computed(() => {
    const days = daysFromToday(nextTrip.value?.start_date);

    if (days === null) {
        return null;
    }

    return days <= 0
        ? 'Under way'
        : days === 1
          ? 'Tomorrow'
          : `${days} days to go`;
});

const featuredOpenTrip = computed(() => props.openTrips[0] ?? null);

const statItems = computed(() =>
    [
        {
            value: props.stats.open_trips,
            label: 'Open trips to join',
            icon: Users,
        },
        {
            value: props.stats.destinations,
            label: 'Destinations shared',
            icon: Globe2,
        },
        { value: props.stats.trips_planned, label: 'Trips planned', icon: Map },
        {
            value: props.stats.travelers,
            label: 'Travelers on board',
            icon: Compass,
        },
    ].filter((item) => item.value > 0),
);

function compactNumber(value: number): string {
    return new Intl.NumberFormat('en', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

const fallbackDestinations = [
    {
        name: 'Coastal escapes',
        region: 'Sun, sea and slow mornings',
        image: '/images/destination-santorini.jpg',
    },
    {
        name: 'The open road',
        region: 'Mountain passes and scenic drives',
        image: '/images/destination-roadtrip.jpg',
    },
    {
        name: 'Island time',
        region: 'Tropical beaches and reefs',
        image: '/images/destination-beach.jpg',
    },
];

const fallbackDestinationImages = fallbackDestinations.map(
    (item) => item.image,
);

const steps = [
    {
        title: 'Describe the trip',
        description:
            'Destination, dates, budget and travel style — in plain language.',
    },
    {
        title: 'Get a day-by-day plan',
        description:
            'AI drafts the itinerary, routes, stays and a packing list.',
    },
    {
        title: 'Refine, share, go',
        description:
            'Tweak with the assistant, invite friends or open it to the community.',
    },
];

const features = [
    {
        icon: Sparkles,
        title: 'AI itineraries',
        text: 'Personalised day-by-day plans in seconds.',
    },
    {
        icon: Route,
        title: 'Road trip routing',
        text: 'Stops, breaks, fuel and scenic drives.',
    },
    {
        icon: Users,
        title: 'Open group trips',
        text: 'Publish a trip and let travelers request to join.',
    },
    {
        icon: CloudSun,
        title: 'Weather aware',
        text: 'Forecasts for every leg of the journey.',
    },
    {
        icon: Map,
        title: 'Interactive maps',
        text: 'See every stop and stay on a live map.',
    },
    {
        icon: Bot,
        title: 'Travel assistant',
        text: 'Ask anything and refine plans by chat.',
    },
];
</script>

<template>
    <Head title="Plan, join and share trips">
        <meta
            name="description"
            content="TripPilot — AI-powered trip planning. Build itineraries, map road trips and join open group trips with fellow travelers."
        />
    </Head>

    <div class="min-h-screen bg-background text-foreground">
        <!-- ═══ HERO ═══ -->
        <section
            class="relative isolate"
            :class="isSearchOpen ? 'z-30' : 'z-0'"
        >
            <img
                src="/images/hero-banner.jpg"
                alt=""
                class="absolute inset-0 -z-10 size-full object-cover"
                fetchpriority="high"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-b from-slate-950/75 via-slate-950/45 to-slate-950/90"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-r from-teal-950/60 via-transparent to-indigo-950/40"
            />

            <PublicHeader variant="overlay" />

            <div
                class="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-10 pb-32 sm:px-6 lg:grid-cols-12 lg:px-8 lg:pt-16 lg:pb-40"
            >
                <div class="lg:col-span-7">
                    <div
                        class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-sm font-medium text-white backdrop-blur-sm"
                    >
                        <template v-if="stats.open_trips > 0">
                            <span class="relative flex size-2">
                                <span
                                    class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                />
                                <span
                                    class="relative inline-flex size-2 rounded-full bg-emerald-400"
                                />
                            </span>
                            {{ stats.open_trips }} open
                            {{
                                stats.open_trips === 1 ? 'trip is' : 'trips are'
                            }}
                            looking for travelers
                        </template>
                        <template v-else>
                            <Sparkles class="size-4 text-sky-300" />
                            AI-powered trip planning
                        </template>
                    </div>

                    <p
                        v-if="user"
                        class="mb-3 text-lg font-medium text-teal-200"
                    >
                        {{ greeting }}, {{ user.first_name }} 👋
                    </p>
                    <h1
                        class="text-4xl leading-[1.05] font-extrabold tracking-tight text-balance text-white sm:text-5xl lg:text-6xl"
                    >
                        Plan it. Share it.
                        <span
                            class="bg-gradient-to-r from-teal-300 via-sky-300 to-violet-300 bg-clip-text text-transparent"
                        >
                            Travel together.
                        </span>
                    </h1>
                    <p
                        class="mt-6 max-w-xl text-lg leading-relaxed text-pretty text-white/80"
                    >
                        Build AI itineraries and road trips in minutes — or find
                        an open group trip and join fellow travelers heading
                        your way.
                    </p>

                    <HomeSearch v-model:open="isSearchOpen" class="mt-8" />

                    <div
                        v-if="categories.length"
                        class="mt-5 flex flex-wrap items-center gap-2"
                    >
                        <span
                            class="text-xs font-medium tracking-wide text-white/60 uppercase"
                        >
                            Trending
                        </span>
                        <Link
                            v-for="category in categories.slice(0, 5)"
                            :key="category.value"
                            :href="
                                openTripsIndex({
                                    query: { category: category.value },
                                })
                            "
                            class="rounded-full border border-white/20 bg-white/5 px-3 py-1 text-xs font-medium text-white/90 backdrop-blur-sm transition hover:border-white/40 hover:bg-white/15"
                        >
                            {{ openTripCategoryLabel(category.value) }}
                            <span class="ml-1 text-white/50">{{
                                category.count
                            }}</span>
                        </Link>
                    </div>

                    <div
                        v-if="!user"
                        class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-white/70"
                    >
                        <Link
                            :href="register()"
                            class="inline-flex items-center gap-1.5 font-semibold text-white hover:text-teal-200"
                        >
                            Start planning — it's free
                            <ArrowRight class="size-4" />
                        </Link>
                        <span>No credit card needed</span>
                    </div>
                </div>

                <!-- Hero side card: your next trip, else the soonest open trip -->
                <div class="hidden lg:col-span-5 lg:block">
                    <div
                        v-if="nextTrip"
                        class="relative ml-auto max-w-sm rotate-1 overflow-hidden rounded-3xl border border-white/20 bg-white/10 p-3 shadow-2xl backdrop-blur-xl transition hover:rotate-0"
                    >
                        <div class="relative h-52 overflow-hidden rounded-2xl">
                            <img
                                v-if="nextTrip.cover_image_thumb_url"
                                :src="nextTrip.cover_image_thumb_url"
                                :alt="nextTrip.title"
                                class="size-full object-cover"
                            />
                            <div v-else class="brand-gradient size-full" />
                            <span
                                class="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-teal-800 shadow"
                            >
                                <CalendarClock class="size-3.5" />
                                {{ nextTripCountdown ?? 'Next up' }}
                            </span>
                        </div>
                        <div class="space-y-1 px-2 pt-4 pb-2 text-white">
                            <p
                                class="text-xs font-semibold tracking-widest text-teal-200 uppercase"
                            >
                                Your next trip
                            </p>
                            <p class="text-xl font-bold">
                                {{ nextTrip.title }}
                            </p>
                            <p
                                class="flex items-center gap-1.5 text-sm text-white/75"
                            >
                                <MapPin class="size-4" />
                                {{
                                    nextTrip.destination ??
                                    'Destination to be decided'
                                }}
                            </p>
                            <p class="text-xs text-white/60 tabular-nums">
                                {{
                                    formatDisplayDateRange(
                                        nextTrip.start_date,
                                        nextTrip.end_date,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-else-if="featuredOpenTrip"
                        class="ml-auto max-w-sm -rotate-1 transition hover:rotate-0"
                    >
                        <p
                            class="mb-3 text-right text-xs font-semibold tracking-widest text-white/70 uppercase"
                        >
                            Next departure
                        </p>
                        <div class="rounded-2xl bg-background shadow-2xl">
                            <HomeOpenTripCard :trip="featuredOpenTrip" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ LIVE STATS ═══ -->
        <section
            v-if="statItems.length"
            class="relative z-10 mx-auto -mt-16 max-w-5xl px-4 sm:px-6 lg:px-8"
        >
            <div
                class="grid grid-cols-2 divide-border/60 overflow-hidden rounded-2xl border border-border/70 bg-background shadow-xl shadow-slate-900/5 md:auto-cols-fr md:grid-flow-col md:grid-cols-none md:divide-x"
            >
                <div
                    v-for="(item, index) in statItems"
                    :key="item.label"
                    class="flex items-center gap-3 p-5"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-md"
                        :class="featureIconAccent(index)"
                    >
                        <component :is="item.icon" class="size-5" />
                    </span>
                    <div>
                        <p
                            class="text-2xl font-bold tracking-tight tabular-nums"
                        >
                            {{ compactNumber(item.value) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ item.label }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ YOUR JOURNEYS (signed in) ═══ -->
        <section v-if="myTrips" class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-sm font-semibold tracking-widest text-primary uppercase"
                        >
                            Your journeys
                        </p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight">
                            {{
                                journeyTab === 'upcoming'
                                    ? 'Coming up next'
                                    : 'Where you have been'
                            }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <div
                            class="inline-flex rounded-full border border-border bg-muted/50 p-1"
                        >
                            <button
                                v-for="tab in ['upcoming', 'past'] as const"
                                :key="tab"
                                type="button"
                                class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                                :class="
                                    journeyTab === tab
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="journeyTab = tab"
                            >
                                {{ tab === 'upcoming' ? 'Upcoming' : 'Past' }}
                                <span
                                    class="ml-1 text-xs text-muted-foreground tabular-nums"
                                >
                                    {{
                                        tab === 'upcoming'
                                            ? myTrips.counts.upcoming +
                                              myTrips.counts.ongoing
                                            : myTrips.counts.past
                                    }}
                                </span>
                            </button>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            as-child
                            class="hidden sm:inline-flex"
                        >
                            <Link :href="tripsIndex()">
                                View all <ArrowRight class="ml-1 size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>

                <div
                    v-if="journeyTrips.length"
                    class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5"
                >
                    <HomePersonalTripCard
                        v-for="(trip, index) in journeyTrips"
                        :key="trip.id"
                        :trip="trip"
                        :index="index"
                    />
                    <Link
                        :href="createTrip()"
                        class="flex aspect-[4/5] flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-border text-muted-foreground transition hover:border-primary/50 hover:bg-primary/5 hover:text-primary sm:aspect-[3/4]"
                    >
                        <span
                            class="flex size-12 items-center justify-center rounded-full bg-muted"
                        >
                            <Plus class="size-6" />
                        </span>
                        <span class="text-sm font-medium">Plan a new trip</span>
                    </Link>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center gap-4 rounded-3xl border border-dashed border-border bg-muted/30 px-6 py-14 text-center"
                >
                    <span
                        class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <component
                            :is="
                                journeyTab === 'upcoming'
                                    ? CalendarClock
                                    : History
                            "
                            class="size-7"
                        />
                    </span>
                    <div class="space-y-1">
                        <p class="text-lg font-semibold">
                            {{
                                journeyTab === 'upcoming'
                                    ? 'Nothing on the calendar yet'
                                    : 'No completed trips yet'
                            }}
                        </p>
                        <p class="max-w-md text-sm text-muted-foreground">
                            {{
                                journeyTab === 'upcoming'
                                    ? 'Plan your next getaway with AI, or join an open trip below.'
                                    : 'Trips you finish will show up here as memories.'
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap justify-center gap-2">
                        <Button as-child>
                            <Link :href="createTrip()"
                                ><Sparkles class="mr-1.5 size-4" /> Plan a
                                trip</Link
                            >
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="createRoadTrip()"
                                ><Route class="mr-1.5 size-4" /> Plan a road
                                trip</Link
                            >
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ OPEN TRIPS ═══ -->
        <section class="app-page-bg py-20" :class="{ 'pt-28': !myTrips }">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="mb-10 flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
                >
                    <div class="max-w-2xl">
                        <p
                            class="text-sm font-semibold tracking-widest text-primary uppercase"
                        >
                            Open trips
                        </p>
                        <h2
                            class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl"
                        >
                            <span class="brand-gradient-text"
                                >Join a trip that's already planned</span
                            >
                        </h2>
                        <p class="mt-3 text-muted-foreground">
                            Treks, bike rides, road trips and weekend escapes
                            organised by fellow travelers — pick a seat and go.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        as-child
                        class="self-start md:self-auto"
                    >
                        <Link :href="openTripsIndex()">
                            Browse all open trips
                            <ArrowRight class="ml-1.5 size-4" />
                        </Link>
                    </Button>
                </div>

                <div
                    v-if="openTrips.length"
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <HomeOpenTripCard
                        v-for="(trip, index) in openTrips"
                        :key="trip.id"
                        :trip="trip"
                        :index="index"
                    />
                </div>

                <div
                    v-else
                    class="flex flex-col items-center gap-4 rounded-3xl border border-dashed border-border bg-card/60 px-6 py-14 text-center"
                >
                    <span
                        class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <Users class="size-7" />
                    </span>
                    <div class="space-y-1">
                        <p class="text-lg font-semibold">
                            Be the first to open a trip
                        </p>
                        <p class="max-w-md text-sm text-muted-foreground">
                            Planning something fun? Publish it as an open trip
                            and let other travelers request to join.
                        </p>
                    </div>
                    <Button as-child>
                        <Link :href="user ? createTrip() : register()">
                            {{
                                user
                                    ? 'Plan & publish a trip'
                                    : 'Create a free account'
                            }}
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <!-- ═══ DESTINATIONS ═══ -->
        <section class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto mb-12 max-w-2xl text-center">
                    <p
                        class="text-sm font-semibold tracking-widest text-primary uppercase"
                    >
                        {{
                            destinations.length
                                ? 'Popular right now'
                                : 'Inspiration'
                        }}
                    </p>
                    <h2
                        class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl"
                    >
                        Where travelers are heading
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        {{
                            destinations.length
                                ? 'Destinations with the most open trips from the community.'
                                : 'Ancient cities, open highways or hidden beaches — plan every detail before you pack.'
                        }}
                    </p>
                </div>

                <div
                    v-if="destinations.length"
                    class="grid auto-rows-[180px] grid-cols-2 gap-4 md:auto-rows-[220px] md:grid-cols-4"
                >
                    <Link
                        v-for="(destination, index) in destinations"
                        :key="destination.label"
                        :href="
                            openTripsIndex({
                                query: { destination: destination.name },
                            })
                        "
                        class="group relative overflow-hidden rounded-2xl shadow-md"
                        :class="{
                            'col-span-2 row-span-2': index === 0,
                            'col-span-2 md:col-span-1':
                                index > 0 && destinations.length === 2,
                        }"
                    >
                        <img
                            :src="
                                destination.cover_image_url ??
                                fallbackDestinationImages[
                                    index % fallbackDestinationImages.length
                                ]
                            "
                            :alt="destination.name"
                            loading="lazy"
                            class="absolute inset-0 size-full object-cover transition-transform duration-700 group-hover:scale-105"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"
                        />
                        <span
                            class="absolute top-3 right-3 flex size-8 items-center justify-center rounded-full bg-white/20 text-white opacity-0 backdrop-blur transition group-hover:opacity-100"
                        >
                            <ArrowUpRight class="size-4" />
                        </span>
                        <div class="absolute inset-x-0 bottom-0 p-4 md:p-5">
                            <span
                                class="mb-2 inline-block rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-medium text-white backdrop-blur-sm"
                            >
                                {{ destination.trip_count }}
                                {{
                                    destination.trip_count === 1
                                        ? 'trip'
                                        : 'trips'
                                }}
                            </span>
                            <h3
                                class="font-bold text-white"
                                :class="
                                    index === 0
                                        ? 'text-2xl md:text-3xl'
                                        : 'text-lg'
                                "
                            >
                                {{ destination.name }}
                            </h3>
                            <p
                                v-if="destination.region"
                                class="truncate text-sm text-white/70"
                            >
                                {{ destination.region }}
                            </p>
                        </div>
                    </Link>
                </div>

                <div v-else class="grid gap-5 md:grid-cols-3">
                    <Link
                        v-for="destination in fallbackDestinations"
                        :key="destination.name"
                        :href="openTripsIndex()"
                        class="group relative overflow-hidden rounded-2xl shadow-md"
                    >
                        <img
                            :src="destination.image"
                            :alt="destination.name"
                            loading="lazy"
                            class="aspect-[4/5] size-full object-cover transition-transform duration-700 group-hover:scale-105 md:aspect-[3/4]"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"
                        />
                        <div class="absolute inset-x-0 bottom-0 p-6">
                            <h3 class="text-xl font-bold text-white">
                                {{ destination.name }}
                            </h3>
                            <p class="mt-1 text-sm text-white/70">
                                {{ destination.region }}
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <!-- ═══ RECENTLY COMPLETED OPEN TRIPS ═══ -->
        <section
            v-if="pastOpenTrips.length"
            class="border-y border-border/60 bg-muted/30 py-20"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-sm font-semibold tracking-widest text-primary uppercase"
                        >
                            Recently wrapped up
                        </p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight">
                            Trips the community just completed
                        </h2>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="self-start sm:self-auto"
                    >
                        <Link :href="openTripsIndex({ query: { past: 1 } })">
                            See past trips <ArrowRight class="ml-1 size-4" />
                        </Link>
                    </Button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <HomeOpenTripCard
                        v-for="(trip, index) in pastOpenTrips"
                        :key="trip.id"
                        :trip="trip"
                        :index="index + 3"
                        compact
                    />
                </div>
            </div>
        </section>

        <!-- ═══ HOW IT WORKS + FEATURES ═══ -->
        <section class="py-24">
            <div
                class="mx-auto grid max-w-7xl gap-14 px-4 sm:px-6 lg:grid-cols-12 lg:px-8"
            >
                <div class="lg:col-span-5">
                    <p
                        class="text-sm font-semibold tracking-widest text-primary uppercase"
                    >
                        How it works
                    </p>
                    <h2
                        class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl"
                    >
                        From idea to itinerary in three steps
                    </h2>
                    <ol
                        class="relative mt-10 space-y-8 border-l border-border pl-8"
                    >
                        <li
                            v-for="(item, index) in steps"
                            :key="item.title"
                            class="relative"
                        >
                            <span
                                class="brand-gradient absolute top-0 -left-[calc(2rem+1rem+1px)] flex size-8 items-center justify-center rounded-full text-sm font-bold text-white shadow-md ring-4 ring-background"
                            >
                                {{ index + 1 }}
                            </span>
                            <h3 class="font-semibold">{{ item.title }}</h3>
                            <p
                                class="mt-1 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ item.description }}
                            </p>
                        </li>
                    </ol>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:col-span-7">
                    <div
                        v-for="(feature, index) in features"
                        :key="feature.title"
                        class="card-vibrant rounded-2xl border p-5"
                    >
                        <span
                            class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-lg"
                            :class="featureIconAccent(index)"
                        >
                            <component :is="feature.icon" class="size-5" />
                        </span>
                        <h3 class="mt-4 font-semibold">{{ feature.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ feature.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ CTA ═══ -->
        <section class="px-4 pb-24 sm:px-6 lg:px-8">
            <div
                class="relative mx-auto max-w-7xl overflow-hidden rounded-3xl shadow-2xl"
            >
                <img
                    src="/images/destination-beach.jpg"
                    alt=""
                    class="absolute inset-0 size-full object-cover"
                    loading="lazy"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-r from-teal-950/95 via-teal-900/80 to-indigo-950/70"
                />
                <div
                    class="relative grid gap-8 px-8 py-16 md:grid-cols-2 md:items-center md:px-14 md:py-20"
                >
                    <div>
                        <h2
                            class="text-3xl font-bold tracking-tight text-white sm:text-4xl"
                        >
                            {{
                                user
                                    ? 'Where to next?'
                                    : 'Your next adventure starts here'
                            }}
                        </h2>
                        <p class="mt-4 max-w-md text-lg text-white/80">
                            {{
                                user
                                    ? 'Spin up a new itinerary or road trip — AI does the heavy lifting.'
                                    : 'Plan smarter, travel together — free to get started.'
                            }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row md:justify-end">
                        <Button
                            size="lg"
                            variant="ghost"
                            as-child
                            class="h-12 bg-white px-7 text-base font-semibold text-teal-800 shadow-xl hover:bg-white/90"
                        >
                            <Link :href="user ? createTrip() : register()">
                                {{
                                    user ? 'Plan a trip' : 'Create free account'
                                }}
                                <ArrowRight class="ml-2 size-5" />
                            </Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            as-child
                            class="h-12 border-white/30 bg-white/5 px-7 text-base text-white backdrop-blur-sm hover:bg-white/15 hover:text-white"
                        >
                            <Link :href="openTripsIndex()"
                                >Discover open trips</Link
                            >
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <PublicFooter />
    </div>
</template>
