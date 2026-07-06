<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    Heart,
    MapPin,
    MapPinned,
    Pencil,
    Plus,
    Users,
} from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { create, index as tripsIndex } from '@/routes/trips';
import { edit, show } from '@/routes/trips';
import type { Trip, TripCounts, TripFilter } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const props = defineProps<{
    trips: Trip[];
    filter: TripFilter;
    counts: TripCounts;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Trips',
                href: tripsIndex(),
            },
        ],
    },
});

const filters: { key: TripFilter; label: string }[] = [
    { key: 'all', label: 'All trips' },
    { key: 'favorites', label: 'Favorites' },
    { key: 'archived', label: 'Archived' },
];

function setFilter(filter: TripFilter): void {
    router.get(tripsIndex({ query: { filter } }), {}, { preserveState: true, preserveScroll: true });
}

function toggleFavorite(trip: Trip): void {
    router.patch(`/trips/${trip.id}/favorite`, {}, { preserveScroll: true });
}

function formatDateRange(trip: Trip): string {
    if (!trip.start_date && !trip.end_date) {
        return 'Dates not set';
    }

    const start = trip.start_date
        ? new Date(trip.start_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
        : '?';
    const end = trip.end_date
        ? new Date(trip.end_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
        : '?';

    return `${start} – ${end}`;
}

function statusVariant(status: Trip['status']): 'default' | 'secondary' | 'outline' {
    if (status === 'planned') {
        return 'default';
    }

    if (status === 'archived') {
        return 'outline';
    }

    return 'secondary';
}
</script>

<template>
    <Head title="Trips" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Trips"
            description="Plan and manage your vacation itineraries."
            :icon="MapPinned"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="mr-2 size-4" />
                        New trip
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2">
            <Button
                v-for="item in filters"
                :key="item.key"
                :variant="props.filter === item.key ? 'default' : 'outline'"
                size="sm"
                @click="setFilter(item.key)"
            >
                {{ item.label }}
                <span class="ml-1.5 text-xs opacity-70">({{ counts[item.key] }})</span>
            </Button>
        </div>

        <EmptyState
            v-if="trips.length === 0"
            :title="filter === 'favorites' ? 'No favorite trips' : filter === 'archived' ? 'No archived trips' : 'No trips yet'"
            :description="
                filter === 'all'
                    ? 'Create your first trip to start building an itinerary. AI generation arrives in Phase 2.'
                    : 'Trips matching this filter will appear here.'
            "
            :icon="MapPinned"
        >
            <template v-if="filter === 'all'" #actions>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="mr-2 size-4" />
                        Create your first trip
                    </Link>
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="trip in trips"
                :key="trip.id"
                class="group border-sidebar-border/70 transition-shadow hover:shadow-md dark:border-sidebar-border"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <CardTitle class="truncate text-base">
                                <Link :href="show(trip.id)" class="hover:text-primary">
                                    {{ trip.title }}
                                </Link>
                            </CardTitle>
                            <p
                                v-if="locationLabel(trip.destination)"
                                class="mt-1 flex items-center gap-1 text-sm text-muted-foreground"
                            >
                                <MapPin class="size-3.5 shrink-0" />
                                {{ locationLabel(trip.destination) }}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="shrink-0"
                            :class="trip.is_favorite ? 'text-rose-500 hover:text-rose-600' : 'text-muted-foreground'"
                            @click="toggleFavorite(trip)"
                        >
                            <Heart class="size-4" :class="trip.is_favorite ? 'fill-current' : ''" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3 pt-0">
                    <div class="flex flex-wrap gap-2">
                        <Badge :variant="statusVariant(trip.status)">{{ trip.status_label }}</Badge>
                        <Badge variant="outline">{{ trip.type_label }}</Badge>
                        <Badge v-if="trip.travel_style_label" variant="secondary">
                            {{ trip.travel_style_label }}
                        </Badge>
                    </div>
                    <div class="space-y-1.5 text-sm text-muted-foreground">
                        <p class="flex items-center gap-2">
                            <Calendar class="size-3.5" />
                            {{ formatDateRange(trip) }}
                        </p>
                        <p class="flex items-center gap-2">
                            <Users class="size-3.5" />
                            {{ trip.travelers }} traveler{{ trip.travelers === 1 ? '' : 's' }}
                        </p>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <Button variant="outline" size="sm" as-child class="flex-1">
                            <Link :href="show(trip.id)">View</Link>
                        </Button>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="edit(trip.id)">
                                <Pencil class="size-4" />
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
