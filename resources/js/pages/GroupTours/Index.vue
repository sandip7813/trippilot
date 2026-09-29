<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar,
    Handshake,
    Mail,
    MapPin,
    Megaphone,
    Plus,
    Send,
    Users,
} from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import type { OpenTripSummary } from '@/components/OpenTripSummaryCard.vue';
import OpenTripSummaryCard from '@/components/OpenTripSummaryCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import TripPager from '@/components/TripPager.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { tripCardAccent } from '@/lib/card-accents';
import { formatDisplayDateRange, formatRelativeTime } from '@/lib/dates';
import { show as openTripShow } from '@/routes/open-trips';
import { create, groupTours, index as tripsIndex, show } from '@/routes/trips';
import type { Paginated } from '@/types/admin';
import type { Trip } from '@/types/trip';
import { locationLabel } from '@/types/trip';

type JoinRequestStatus = 'pending' | 'accepted' | 'declined' | 'withdrawn';

type RequestedTour = {
    trip: OpenTripSummary;
    status: JoinRequestStatus;
    status_label: string;
    requested_at: string | null;
};

type ContactedTour = {
    trip: OpenTripSummary;
    subject: string;
    contacted_at: string | null;
};

const props = defineProps<{
    owned: Paginated<Trip>;
    requested: RequestedTour[];
    joined: OpenTripSummary[];
    contacted: ContactedTour[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: tripsIndex() },
            { title: 'Group Tours', href: groupTours() },
        ],
    },
});

const tabs = [
    { key: 'owned', label: 'Tours owned', icon: Megaphone },
    { key: 'requested', label: 'Requested to join', icon: Send },
    { key: 'joined', label: 'Joined', icon: Handshake },
    { key: 'contacted', label: 'Contacted', icon: Mail },
] as const;

function tabCount(key: (typeof tabs)[number]['key']): number {
    return key === 'owned' ? props.owned.total : props[key].length;
}

function coverThumbUrl(trip: Trip): string | null {
    return trip.cover_image_thumb_url ?? trip.cover_image_url ?? null;
}

function requestStatusVariant(
    status: JoinRequestStatus,
): 'default' | 'secondary' | 'outline' {
    if (status === 'pending') {
        return 'default';
    }

    if (status === 'declined') {
        return 'outline';
    }

    return 'secondary';
}
</script>

<template>
    <Head title="Group Tours" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Group Tours"
            description="Open trips you own, and the ones you've requested to join, joined, or contacted the organizer about."
            :icon="Megaphone"
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

        <Tabs default-value="owned">
            <TabsList class="w-full flex-wrap sm:w-fit">
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.key"
                    :value="tab.key"
                    class="gap-1.5"
                >
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.label }}
                    <span class="rounded-full bg-primary/10 px-1.5 text-xs text-primary">
                        {{ tabCount(tab.key) }}
                    </span>
                </TabsTrigger>
            </TabsList>

            <!-- Tours owned -->
            <TabsContent value="owned" class="flex flex-col gap-4">
                <EmptyState
                    v-if="owned.data.length === 0"
                    title="No group tours yet"
                    description="Publish a trip as an open trip to list it here and let other travelers discover, contact you, and request to join."
                    :icon="Megaphone"
                >
                    <template #actions>
                        <Button as-child>
                            <Link :href="create()">
                                <Plus class="mr-2 size-4" />
                                Create a trip
                            </Link>
                        </Button>
                    </template>
                </EmptyState>

                <template v-else>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <Card
                            v-for="(trip, index) in owned.data"
                            :key="trip.id"
                            class="card-vibrant group !flex-row items-stretch gap-0 overflow-hidden !py-0"
                        >
                            <Link
                                v-if="coverThumbUrl(trip)"
                                :href="show(trip.id)"
                                class="relative w-[7.5rem] shrink-0 self-stretch overflow-hidden sm:w-32"
                            >
                                <img
                                    :src="coverThumbUrl(trip) ?? undefined"
                                    :alt="`${trip.title} cover`"
                                    width="384"
                                    height="512"
                                    loading="lazy"
                                    decoding="async"
                                    class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                                />
                            </Link>
                            <div
                                v-else
                                class="w-1 shrink-0 bg-gradient-to-b"
                                :class="tripCardAccent(index)"
                            />

                            <div class="flex min-w-0 flex-1 flex-col justify-between p-4">
                                <div>
                                    <h3 class="truncate text-base leading-tight font-semibold">
                                        <Link
                                            :href="show(trip.id)"
                                            class="transition-colors hover:text-primary"
                                        >
                                            {{ trip.title }}
                                        </Link>
                                    </h3>
                                    <p
                                        v-if="locationLabel(trip.destination)"
                                        class="mt-1 flex items-center gap-1 text-sm text-muted-foreground"
                                    >
                                        <MapPin
                                            class="size-3.5 shrink-0 text-teal-600 dark:text-teal-400"
                                        />
                                        <span class="truncate">{{
                                            locationLabel(trip.destination)
                                        }}</span>
                                    </p>

                                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                                        <Badge variant="outline" class="text-xs">{{
                                            trip.type_label
                                        }}</Badge>
                                        <Badge
                                            variant="secondary"
                                            class="bg-teal-500/10 text-xs text-teal-700 dark:text-teal-300"
                                        >
                                            Open trip
                                        </Badge>
                                    </div>

                                    <div class="mt-2.5 space-y-1 text-sm text-muted-foreground">
                                        <p class="flex items-center gap-2">
                                            <Calendar
                                                class="size-3.5 shrink-0 text-sky-600 dark:text-sky-400"
                                            />
                                            <span class="truncate">{{
                                                formatDisplayDateRange(
                                                    trip.start_date,
                                                    trip.end_date,
                                                )
                                            }}</span>
                                        </p>
                                        <p class="flex items-center gap-2">
                                            <Users
                                                class="size-3.5 shrink-0 text-amber-600 dark:text-amber-400"
                                            />
                                            {{ trip.travelers }} traveler{{
                                                trip.travelers === 1 ? '' : 's'
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-3 flex gap-2">
                                    <Button size="sm" as-child class="min-w-0 flex-1">
                                        <Link :href="show(trip.id)">View trip</Link>
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    </div>

                    <TripPager :paginated="owned" :only="['owned']" />
                </template>
            </TabsContent>

            <!-- Requested to join -->
            <TabsContent value="requested" class="flex flex-col gap-4">
                <EmptyState
                    v-if="requested.length === 0"
                    title="No join requests yet"
                    description="Open trips you've asked to join will appear here."
                    :icon="Send"
                />

                <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <OpenTripSummaryCard
                        v-for="entry in requested"
                        :key="entry.trip.id"
                        :trip="entry.trip"
                        :href="openTripShow(entry.trip.id).url"
                    >
                        <template #badge>
                            <Badge
                                :variant="requestStatusVariant(entry.status)"
                                class="text-xs"
                            >
                                {{ entry.status_label }}
                            </Badge>
                        </template>
                        <template v-if="entry.requested_at" #meta>
                            <p class="text-xs text-muted-foreground">
                                Requested {{ formatRelativeTime(entry.requested_at) }}
                            </p>
                        </template>
                    </OpenTripSummaryCard>
                </div>
            </TabsContent>

            <!-- Joined -->
            <TabsContent value="joined" class="flex flex-col gap-4">
                <EmptyState
                    v-if="joined.length === 0"
                    title="No joined tours yet"
                    description="Open trips you've been accepted into will appear here."
                    :icon="Handshake"
                />

                <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <OpenTripSummaryCard
                        v-for="trip in joined"
                        :key="trip.id"
                        :trip="trip"
                        :href="show(trip.id).url"
                    >
                        <template #badge>
                            <Badge
                                variant="secondary"
                                class="bg-emerald-500/10 text-xs text-emerald-700 dark:text-emerald-300"
                            >
                                Member
                            </Badge>
                        </template>
                    </OpenTripSummaryCard>
                </div>
            </TabsContent>

            <!-- Contacted -->
            <TabsContent value="contacted" class="flex flex-col gap-4">
                <EmptyState
                    v-if="contacted.length === 0"
                    title="No contacted organizers yet"
                    description="Open trips whose organizer you've messaged will appear here."
                    :icon="Mail"
                />

                <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <OpenTripSummaryCard
                        v-for="entry in contacted"
                        :key="entry.trip.id"
                        :trip="entry.trip"
                        :href="openTripShow(entry.trip.id).url"
                    >
                        <template #meta>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ entry.subject }}
                            </p>
                            <p v-if="entry.contacted_at" class="text-xs text-muted-foreground">
                                Contacted {{ formatRelativeTime(entry.contacted_at) }}
                            </p>
                        </template>
                    </OpenTripSummaryCard>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
