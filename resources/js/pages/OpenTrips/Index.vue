<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleAlert, Compass, History, MapPin, Tag, Users, Wallet } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import TripPager from '@/components/TripPager.vue';
import TripPilotBrand from '@/components/TripPilotBrand.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dashboard, login } from '@/routes';
import { index as openTripsIndex, show as openTripShow } from '@/routes/open-trips';
import type { Paginated } from '@/types/admin';

type OpenTripSummary = {
    id: string;
    title: string;
    type_label: string;
    destination: { label: string } | null;
    start_date: string | null;
    end_date: string | null;
    cover_image_thumb_url: string | null;
    is_past: boolean;
    is_joinable: boolean;
    organizer: { name: string } | null;
    group: {
        category: string | null;
        seats_left: number | null;
        max_group_size: number | null;
        cost_model_label: string | null;
        cost_amount: number | null;
        cost_currency: string | null;
    };
};

defineProps<{
    trips: Paginated<OpenTripSummary>;
    showPast: boolean;
    filters: { category?: string; destination?: string };
}>();

const page = usePage();
const isAuthenticated = () => Boolean(page.props.auth?.user);
</script>

<template>
    <Head title="Discover open trips" />

    <div class="min-h-screen bg-background">
        <header
            class="border-b bg-background/80 px-4 py-4 backdrop-blur-sm sm:px-6"
        >
            <div
                class="mx-auto flex max-w-6xl items-center justify-between"
            >
                <Link :href="isAuthenticated() ? dashboard() : '/'">
                    <TripPilotBrand />
                </Link>
                <Button as-child size="sm">
                    <Link :href="isAuthenticated() ? dashboard() : login()">
                        {{ isAuthenticated() ? 'Dashboard' : 'Log in' }}
                    </Link>
                </Button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <div class="mb-8 flex flex-col gap-1">
                <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                    <span class="brand-gradient-text">Discover open trips</span>
                </h1>
                <p class="max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Trips other travelers have opened up for others to join —
                    treks, bike rides, road trips and more. Log in to contact
                    the organizer or request to join.
                </p>
            </div>

            <div class="mb-6 flex flex-wrap gap-2">
                <Button
                    :variant="!showPast ? 'default' : 'outline'"
                    as-child
                    size="sm"
                >
                    <Link :href="openTripsIndex()">Upcoming</Link>
                </Button>
                <Button
                    :variant="showPast ? 'default' : 'outline'"
                    as-child
                    size="sm"
                >
                    <Link :href="openTripsIndex({ query: { past: 1 } })">
                        Past trips
                    </Link>
                </Button>
            </div>

            <EmptyState
                v-if="trips.data.length === 0"
                :icon="Compass"
                title="No open trips yet"
                :description="showPast
                    ? 'No past open trips to show.'
                    : 'Nobody has opened a trip up for others to join right now. Check back soon.'"
            />

            <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="trip in trips.data"
                    :key="trip.id"
                    :href="openTripShow(trip.id)"
                >
                    <Card class="card-vibrant h-full overflow-hidden transition hover:shadow-lg">
                        <div
                            class="h-36 w-full bg-cover bg-center brand-gradient"
                            :style="trip.cover_image_thumb_url
                                ? { backgroundImage: `url(${trip.cover_image_thumb_url})` }
                                : undefined"
                        />
                        <div class="flex flex-col gap-2 px-5">
                            <div class="flex items-center justify-between gap-2">
                                <Badge class="gap-1.5 border-0 bg-secondary text-secondary-foreground">
                                    <Tag class="size-3" />
                                    {{ trip.type_label }}
                                </Badge>
                                <Badge
                                    v-if="trip.is_past"
                                    variant="outline"
                                    class="gap-1.5 text-muted-foreground"
                                >
                                    <History class="size-3" />
                                    Past trip
                                </Badge>
                                <Badge
                                    v-else-if="trip.group.seats_left === 0"
                                    class="gap-1.5 border-0 bg-amber-500 text-white"
                                >
                                    <CircleAlert class="size-3" />
                                    Full
                                </Badge>
                            </div>
                            <h2 class="font-semibold leading-snug">{{ trip.title }}</h2>
                            <p
                                v-if="trip.destination"
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <MapPin class="size-4 shrink-0" />
                                {{ trip.destination.label }}
                            </p>
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span v-if="trip.organizer">
                                    By {{ trip.organizer.name }}
                                </span>
                                <span
                                    v-if="trip.group.max_group_size"
                                    class="flex items-center gap-1"
                                >
                                    <Users class="size-3.5" />
                                    {{ trip.group.seats_left }}/{{ trip.group.max_group_size }} seats
                                </span>
                            </div>
                            <p
                                v-if="trip.group.cost_model_label"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Wallet class="size-3.5 shrink-0" />
                                <span v-if="trip.group.cost_amount">
                                    {{ trip.group.cost_currency }} {{ trip.group.cost_amount }} / person
                                </span>
                                <span v-else>{{ trip.group.cost_model_label }}</span>
                            </p>
                        </div>
                    </Card>
                </Link>
            </div>

            <div class="mt-8">
                <TripPager
                    :paginated="trips"
                    :only="['trips']"
                />
            </div>
        </main>
    </div>
</template>
