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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as roadTripsIndex } from '@/routes/road-trips';
import { index as tripsIndex } from '@/routes/trips';

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
        description: 'Create an AI-assisted itinerary for your next getaway.',
        href: tripsIndex(),
        icon: Map,
        badge: 'Phase 1',
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
        <!-- Welcome banner -->
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
                    <Link :href="tripsIndex()">
                        <Sparkles class="mr-2 size-4" />
                        New trip
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard
                label="Trips"
                value="0"
                hint="Create your first itinerary in Phase 1"
                :icon="MapPinned"
                accent="primary"
            />
            <StatCard
                label="Road trips"
                value="0"
                hint="Route planning arrives in Phase 5"
                :icon="Route"
                accent="sky"
            />
            <StatCard
                label="Upcoming"
                value="—"
                hint="No scheduled departures yet"
                :icon="CalendarDays"
                accent="amber"
            />
        </div>

        <!-- Quick actions -->
        <div>
            <PageHeader
                title="Quick actions"
                description="Jump into the features we're building next."
            />
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

        <!-- Getting started -->
        <Card class="border-sidebar-border/70 dark:border-sidebar-border">
            <CardHeader>
                <CardTitle class="text-base">Getting started</CardTitle>
                <CardDescription>
                    TripPilot is under active development. Here's what's coming next.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ol class="space-y-3 text-sm">
                    <li class="flex items-start gap-3">
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                        >
                            1
                        </span>
                        <div>
                            <p class="font-medium">Trip CRUD</p>
                            <p class="text-muted-foreground">
                                Create, edit, and manage vacation itineraries stored in MongoDB.
                            </p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                        >
                            2
                        </span>
                        <div>
                            <p class="font-medium">AI generation</p>
                            <p class="text-muted-foreground">
                                Generate full day-by-day plans with Google Gemini.
                            </p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                        >
                            3
                        </span>
                        <div>
                            <p class="font-medium">Maps & road trips</p>
                            <p class="text-muted-foreground">
                                Interactive maps, routing, and weather along your route.
                            </p>
                        </div>
                    </li>
                </ol>
            </CardContent>
        </Card>
    </div>
</template>
