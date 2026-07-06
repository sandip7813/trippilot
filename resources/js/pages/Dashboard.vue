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
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as roadTripsIndex } from '@/routes/road-trips';
import { create, index as tripsIndex, show } from '@/routes/trips';
import type { Trip } from '@/types/trip';

defineProps<{
    stats: {
        trips: number;
        road_trips: number;
        upcoming: string | null;
    };
    recentTrips: Trip[];
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
            class="relative overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/10 via-background to-sky-500/5 p-6 md:p-8"
        >
            <div
                class="pointer-events-none absolute -right-8 -top-8 size-40 rounded-full bg-primary/10 blur-2xl"
            />
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-primary">Welcome back</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight md:text-3xl">
                        Hey, {{ userName }} 👋
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
                :value="stats.upcoming ?? '—'"
                hint="Next scheduled departure"
                :icon="CalendarDays"
                accent="amber"
            />
        </div>

        <Card v-if="recentTrips.length > 0" class="border-sidebar-border/70 dark:border-sidebar-border">
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
                    class="flex items-center justify-between rounded-lg border border-border/60 p-3 transition-colors hover:bg-muted/40"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ trip.title }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ trip.destination ?? 'No destination' }}
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
                    class="group border-sidebar-border/70 transition-shadow hover:shadow-md dark:border-sidebar-border"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div
                                class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
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
