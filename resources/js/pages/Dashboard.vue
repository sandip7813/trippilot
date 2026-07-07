<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    Map,
    MapPinned,
    Route,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { formatDisplayDate } from '@/lib/dates';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as roadTripsIndex } from '@/routes/road-trips';
import { create, index as tripsIndex, show } from '@/routes/trips';
import type { Trip } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const props = defineProps<{
    stats: {
        trips: number;
        road_trips: number;
        upcoming: string | null;
    };
    recentTrips: Trip[];
}>();

const upcomingLabel = computed(() =>
    props.stats.upcoming ? formatDisplayDate(props.stats.upcoming) : '—',
);

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
const userName = page.props.auth.user?.name?.split(' ')[0] ?? 'Traveler';

const quickActions = [
    {
        title: 'Plan a vacation',
        description: 'Create a new trip and start building your itinerary.',
        href: create(),
        icon: Map,
    },
    {
        title: 'Map a road trip',
        description: 'Plot routes, stops, and scenic drives across the map.',
        href: roadTripsIndex(),
        icon: Route,
        badge: 'Phase 5',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="card-vibrant relative overflow-hidden rounded-2xl p-6 md:p-8"
        >
            <div class="brand-gradient absolute inset-x-0 top-0 h-1.5 opacity-90" />
            <div
                class="pointer-events-none absolute -right-12 -top-12 size-48 rounded-full bg-violet-500/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute -bottom-8 left-1/3 size-36 rounded-full bg-teal-500/15 blur-3xl"
            />
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400">
                        Welcome back
                    </p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight md:text-3xl">
                        Hey, {{ userName }}
                        <span class="ml-1">✈️</span>
                    </h1>
                    <p class="mt-2 max-w-lg text-sm text-muted-foreground">
                        Your travel command center is ready. Start a new trip or pick up where
                        you left off.
                    </p>
                </div>
                <Button as-child class="shrink-0 self-start sm:self-center">
                    <Link :href="create()">
                        <Sparkles class="mr-2 size-4" />
                        New trip
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard
                label="Trips"
                :value="stats.trips"
                hint="Active vacation itineraries"
                :icon="MapPinned"
                accent="primary"
            />
            <StatCard
                label="Road trips"
                :value="stats.road_trips"
                hint="Road trip planning in Phase 5"
                :icon="Route"
                accent="sky"
            />
            <StatCard
                label="Upcoming"
                :value="upcomingLabel"
                hint="Next scheduled departure"
                :icon="CalendarDays"
                accent="amber"
            />
        </div>

        <Card v-if="recentTrips.length > 0" class="card-vibrant overflow-hidden">
            <div class="h-1 bg-gradient-to-r from-teal-500 to-violet-500" />
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle class="text-base">Recent trips</CardTitle>
                    <CardDescription>Pick up where you left off</CardDescription>
                </div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="tripsIndex()">View all</Link>
                </Button>
            </CardHeader>
            <CardContent class="space-y-3">
                <Link
                    v-for="trip in recentTrips"
                    :key="trip.id"
                    :href="show(trip.id)"
                    class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/30 p-3 transition-all hover:border-teal-500/30 hover:bg-teal-500/5"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ trip.title }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ locationLabel(trip.destination) ?? 'No destination' }}
                        </p>
                    </div>
                    <Badge variant="outline">{{ trip.status_label }}</Badge>
                </Link>
            </CardContent>
        </Card>

        <div>
            <PageHeader title="Quick actions" description="Jump into planning." />
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <Card
                    v-for="action in quickActions"
                    :key="action.title"
                    class="card-vibrant group overflow-hidden"
                >
                    <div class="brand-gradient h-1 opacity-80" />
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div
                                class="brand-gradient flex size-11 items-center justify-center rounded-xl text-white shadow-md shadow-teal-500/20"
                            >
                                <component :is="action.icon" class="size-5" />
                            </div>
                            <span
                                v-if="action.badge"
                                class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ action.badge }}
                            </span>
                        </div>
                        <CardTitle class="text-base">{{ action.title }}</CardTitle>
                        <CardDescription>{{ action.description }}</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <Button variant="ghost" size="sm" as-child class="group/btn -ml-2">
                            <Link :href="action.href">
                                Explore
                                <ArrowRight
                                    class="ml-1 size-4 transition-transform group-hover/btn:translate-x-0.5"
                                />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
