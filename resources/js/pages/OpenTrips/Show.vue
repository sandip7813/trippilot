<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    Backpack,
    Calendar,
    CircleAlert,
    Clock,
    Compass,
    Flag,
    Gauge,
    History,
    Info,
    ListChecks,
    MapPin,
    ShieldCheck,
    Sparkles,
    Tag,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, defineAsyncComponent, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import TripHubRouteStopsList from '@/components/trip-hub/TripHubRouteStopsList.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { shortCityLabel } from '@/composables/useTripRouteStops';
import { formatDisplayDate, formatDisplayDateRange } from '@/lib/dates';
import { login } from '@/routes';
import { edit as editTrip, report as reportTrip } from '@/routes/trips';
import { store as storeInquiry } from '@/routes/trips/inquiries';
import {
    destroy as withdrawJoinRequest,
    store as storeJoinRequest,
} from '@/routes/trips/join-requests';
import type { TripRouteMapPoint, TripRouteStop } from '@/types/trip';

const TripRouteMap = defineAsyncComponent({
    loader: () => import('@/components/trip-hub/TripRouteMap.vue'),
});

type OpenTripRoute = {
    chain: string[];
    timeline: TripRouteStop[];
    map_points: TripRouteMapPoint[];
};

type OpenTripItineraryActivity = {
    time?: string | null;
    title: string;
    notes?: string | null;
};

type OpenTripItineraryDay = {
    day: number;
    date?: string | null;
    title?: string;
    activities?: OpenTripItineraryActivity[];
};

type OpenTripItinerary = {
    days: OpenTripItineraryDay[];
    summary: string;
    packing_list: string[];
};

type OpenTripOverview = {
    id: string;
    title: string;
    type_label: string;
    travel_style_label: string | null;
    origin: { label: string } | null;
    destination: { label: string } | null;
    start_date: string | null;
    end_date: string | null;
    cover_image_url: string | null;
    is_past: boolean;
    is_joinable: boolean;
    organizer: { name: string } | null;
    route: OpenTripRoute;
    itinerary: OpenTripItinerary | null;
    group: {
        category: string | null;
        difficulty: string | null;
        requirements: string | null;
        meeting_point: string | null;
        rules: string | null;
        join_deadline: string | null;
        max_group_size: number | null;
        seats_left: number | null;
        member_count: number;
        member_names_visible: boolean;
        cost_model: string | null;
        cost_model_label: string | null;
        cost_amount: number | null;
        cost_currency: string | null;
        cost_inclusions: string | null;
    };
};

type MyJoinRequest = {
    id: string;
    status: string;
    is_pending: boolean;
} | null;

const { trip, isOwner, isMember, myJoinRequest } = defineProps<{
    trip: OpenTripOverview;
    isOwner: boolean;
    isMember: boolean;
    myJoinRequest: MyJoinRequest;
}>();

const page = usePage();
const isAuthenticated = () => Boolean(page.props.auth?.user);

const reportDialogOpen = ref(false);

function humanize(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

const categoryLabel = computed(() => humanize(trip.group.category));
const difficultyLabel = computed(() => humanize(trip.group.difficulty));

const organizerInitials = computed(() => {
    if (!trip.organizer?.name) {
        return '?';
    }

    return trip.organizer.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
});

const seatsFilledPercent = computed(() => {
    if (!trip.group.max_group_size) {
        return 0;
    }

    const filled =
        trip.group.max_group_size -
        (trip.group.seats_left ?? trip.group.max_group_size);

    return Math.min(
        100,
        Math.max(0, Math.round((filled / trip.group.max_group_size) * 100)),
    );
});

const activeTab = ref('overview');

function goToTab(tab: string): void {
    activeTab.value = tab;
    document
        .getElementById('trip-tabs')
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const costValue = computed(() =>
    trip.group.cost_amount
        ? `${trip.group.cost_currency ?? ''} ${trip.group.cost_amount}`.trim()
        : (trip.group.cost_model_label ?? null),
);

const quickFacts = computed(() => {
    const facts: { icon: Component; text: string }[] = [];

    if (trip.destination?.label) {
        facts.push({ icon: MapPin, text: trip.destination.label });
    }

    facts.push({
        icon: Calendar,
        text: formatDisplayDateRange(trip.start_date, trip.end_date),
    });

    if (categoryLabel.value) {
        facts.push({
            icon: Compass,
            text: difficultyLabel.value
                ? `${categoryLabel.value} · ${difficultyLabel.value}`
                : categoryLabel.value,
        });
    }

    return facts;
});

const hasOverviewDetails = computed(
    () =>
        Boolean(trip.group.requirements) ||
        Boolean(trip.group.meeting_point) ||
        Boolean(trip.group.rules) ||
        Boolean(trip.group.cost_inclusions),
);

const hasRouteTimeline = computed(() => trip.route.timeline.length >= 2);
const canShowRouteMap = computed(() => trip.route.map_points.length > 0);
</script>

<template>
    <Head :title="trip.title" />

    <div class="flex min-h-screen flex-col bg-background">
        <PublicHeader container-class="max-w-5xl" />

        <main
            class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:px-6 sm:py-10"
        >
            <!-- Hero -->
            <div
                class="relative overflow-hidden rounded-3xl shadow-lg shadow-teal-500/10"
            >
                <div
                    class="brand-gradient h-56 w-full bg-cover bg-center sm:h-72"
                    :style="
                        trip.cover_image_url
                            ? {
                                  backgroundImage: `url(${trip.cover_image_url})`,
                              }
                            : undefined
                    "
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent"
                />

                <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            class="gap-1.5 border-0 bg-white px-3 py-1 text-foreground shadow-md"
                        >
                            <Tag class="size-3" />
                            {{ trip.type_label }}
                        </Badge>
                        <Badge
                            v-if="trip.travel_style_label"
                            class="gap-1.5 border border-white/30 bg-white/10 px-3 py-1 text-white backdrop-blur-md"
                        >
                            <Sparkles class="size-3" />
                            {{ trip.travel_style_label }}
                        </Badge>
                        <Badge
                            v-if="trip.is_past"
                            class="gap-1.5 border border-white/30 bg-white/10 px-3 py-1 text-white backdrop-blur-md"
                        >
                            <History class="size-3" />
                            Past trip
                        </Badge>
                        <Badge
                            v-else-if="trip.group.seats_left === 0"
                            class="gap-1.5 border-0 bg-amber-500 px-3 py-1 text-white shadow-md"
                        >
                            <CircleAlert class="size-3" />
                            Full
                        </Badge>
                    </div>

                    <h1
                        class="mt-2 text-2xl font-bold tracking-tight text-white drop-shadow-sm sm:text-4xl"
                    >
                        {{ trip.title }}
                    </h1>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <div
                            v-if="trip.organizer"
                            class="flex items-center gap-2"
                        >
                            <Avatar class="size-7 border border-white/40">
                                <AvatarFallback
                                    class="bg-white/20 text-xs font-semibold text-white"
                                >
                                    {{ organizerInitials }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="text-sm text-white/90">
                                Organized by {{ trip.organizer.name }}
                            </span>
                        </div>
                        <Link
                            v-if="isOwner"
                            :href="editTrip(trip.id)"
                            class="text-sm font-medium text-white underline underline-offset-2 hover:text-white/80"
                        >
                            Manage this trip
                        </Link>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3 lg:items-start">
                <aside class="order-first lg:sticky lg:top-6 lg:order-last">
                    <Card class="card-vibrant overflow-hidden !py-0">
                        <div class="brand-gradient h-1.5" />
                        <div class="space-y-5 p-5">
                            <div v-if="costValue">
                                <p
                                    class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        trip.group.cost_amount
                                            ? 'Cost per person'
                                            : 'Cost'
                                    }}
                                </p>
                                <p
                                    class="mt-0.5 font-bold tracking-tight"
                                    :class="
                                        trip.group.cost_amount
                                            ? 'brand-gradient-text text-3xl'
                                            : 'text-lg'
                                    "
                                >
                                    {{ costValue }}
                                </p>
                                <p
                                    v-if="
                                        trip.group.cost_amount &&
                                        trip.group.cost_model_label
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ trip.group.cost_model_label }}
                                </p>
                            </div>

                            <dl class="space-y-3 text-sm">
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <dt
                                        class="flex items-center gap-2 text-muted-foreground"
                                    >
                                        <Calendar class="size-4" /> Dates
                                    </dt>
                                    <dd class="text-right font-medium">
                                        {{
                                            formatDisplayDateRange(
                                                trip.start_date,
                                                trip.end_date,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div
                                    v-if="trip.group.join_deadline"
                                    class="flex items-start justify-between gap-3"
                                >
                                    <dt
                                        class="flex items-center gap-2 text-muted-foreground"
                                    >
                                        <Clock class="size-4" /> Join by
                                    </dt>
                                    <dd class="text-right font-medium">
                                        {{
                                            formatDisplayDate(
                                                trip.group.join_deadline.slice(
                                                    0,
                                                    10,
                                                ),
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="trip.group.max_group_size"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span
                                        class="flex items-center gap-2 text-muted-foreground"
                                    >
                                        <Users class="size-4" /> Seats
                                    </span>
                                    <span class="font-medium">
                                        {{ trip.group.seats_left ?? 0 }} of
                                        {{ trip.group.max_group_size }} left
                                    </span>
                                </div>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="brand-gradient h-full rounded-full transition-all"
                                        :style="{
                                            width: `${seatsFilledPercent}%`,
                                        }"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ trip.group.member_count }} traveler{{
                                        trip.group.member_count === 1 ? '' : 's'
                                    }}
                                    joined
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Button v-if="isOwner" as-child class="w-full">
                                    <Link :href="editTrip(trip.id)"
                                        >Manage this trip</Link
                                    >
                                </Button>
                                <template v-else>
                                    <p
                                        v-if="isMember"
                                        class="rounded-lg bg-emerald-500/10 px-3 py-2 text-center text-sm font-medium text-emerald-700 dark:text-emerald-300"
                                    >
                                        You're a member of this trip
                                    </p>
                                    <p
                                        v-else-if="myJoinRequest?.is_pending"
                                        class="rounded-lg bg-amber-500/10 px-3 py-2 text-center text-sm font-medium text-amber-700 dark:text-amber-300"
                                    >
                                        Your request is pending
                                    </p>
                                    <Button
                                        v-else-if="!trip.is_past"
                                        class="w-full"
                                        :disabled="!trip.is_joinable"
                                        @click="goToTab('join')"
                                    >
                                        <Sparkles class="mr-2 size-4" />
                                        {{
                                            trip.is_joinable
                                                ? 'Request to join'
                                                : 'Not accepting requests'
                                        }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="w-full"
                                        @click="goToTab('contact')"
                                    >
                                        Contact organizer
                                    </Button>
                                </template>
                            </div>
                        </div>
                    </Card>
                </aside>

                <div class="min-w-0 space-y-6 lg:col-span-2">
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="fact in quickFacts"
                            :key="fact.text"
                            class="inline-flex items-center gap-2 rounded-full border bg-card/70 px-3.5 py-1.5 text-sm font-medium"
                        >
                            <component
                                :is="fact.icon"
                                class="size-4 text-primary"
                            />
                            {{ fact.text }}
                        </span>
                    </div>

                    <!-- Tabs -->
                    <Tabs
                        id="trip-tabs"
                        v-model="activeTab"
                        class="scroll-mt-6"
                    >
                        <TabsList class="w-full flex-wrap sm:w-fit">
                            <TabsTrigger value="overview">
                                <Info class="size-4" />
                                Overview
                            </TabsTrigger>
                            <TabsTrigger v-if="hasRouteTimeline" value="route">
                                <Compass class="size-4" />
                                Route
                            </TabsTrigger>
                            <TabsTrigger
                                v-if="trip.itinerary"
                                value="itinerary"
                            >
                                <Backpack class="size-4" />
                                Itinerary
                            </TabsTrigger>
                            <TabsTrigger v-if="!trip.is_past" value="join">
                                <Sparkles class="size-4" />
                                Join
                            </TabsTrigger>
                            <TabsTrigger value="contact">
                                <Users class="size-4" />
                                Contact
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="overview" class="mt-6 space-y-4">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <Card
                                    v-if="trip.group.requirements"
                                    class="card-vibrant overflow-hidden"
                                >
                                    <CardContent class="flex gap-3 py-4">
                                        <ListChecks
                                            class="mt-0.5 size-5 shrink-0 text-teal-600 dark:text-teal-400"
                                        />
                                        <div>
                                            <p class="text-sm font-semibold">
                                                Requirements
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                            >
                                                {{ trip.group.requirements }}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card
                                    v-if="trip.group.meeting_point"
                                    class="card-vibrant overflow-hidden"
                                >
                                    <CardContent class="flex gap-3 py-4">
                                        <MapPin
                                            class="mt-0.5 size-5 shrink-0 text-sky-600 dark:text-sky-400"
                                        />
                                        <div>
                                            <p class="text-sm font-semibold">
                                                Meeting point
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                            >
                                                {{ trip.group.meeting_point }}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card
                                    v-if="trip.group.cost_inclusions"
                                    class="card-vibrant overflow-hidden"
                                >
                                    <CardContent class="flex gap-3 py-4">
                                        <ShieldCheck
                                            class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <div>
                                            <p class="text-sm font-semibold">
                                                What's included
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                            >
                                                {{ trip.group.cost_inclusions }}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card
                                    v-if="trip.group.rules"
                                    class="card-vibrant overflow-hidden"
                                >
                                    <CardContent class="flex gap-3 py-4">
                                        <Gauge
                                            class="mt-0.5 size-5 shrink-0 text-violet-600 dark:text-violet-400"
                                        />
                                        <div>
                                            <p class="text-sm font-semibold">
                                                Group rules
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                            >
                                                {{ trip.group.rules }}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <p
                                v-if="!hasOverviewDetails"
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                The organizer hasn't added extra details for
                                this trip yet — use the Contact tab to ask them
                                directly.
                            </p>

                            <Alert class="mt-6 border-dashed">
                                <Info class="size-4" />
                                <AlertDescription>
                                    TripPilot connects travelers and does not
                                    organize this trip or handle payments.
                                    Coordinate costs and logistics directly with
                                    the organizer.
                                </AlertDescription>
                            </Alert>
                        </TabsContent>

                        <TabsContent
                            v-if="hasRouteTimeline"
                            value="route"
                            class="mt-6 space-y-4"
                        >
                            <Card class="card-vibrant overflow-hidden">
                                <CardContent class="py-4">
                                    <p
                                        class="flex items-center gap-1.5 text-sm font-semibold"
                                    >
                                        <Compass
                                            class="size-4 shrink-0 text-violet-600 dark:text-violet-400"
                                        />
                                        Route
                                    </p>

                                    <div
                                        v-if="trip.route.chain.length > 0"
                                        class="mt-2 flex flex-wrap items-center gap-x-1 gap-y-0.5 text-xs text-muted-foreground"
                                    >
                                        <template
                                            v-for="(label, index) in trip.route
                                                .chain"
                                            :key="`route-chain-${index}-${label}`"
                                        >
                                            <span>{{
                                                shortCityLabel(label)
                                            }}</span>
                                            <span
                                                v-if="
                                                    index <
                                                    trip.route.chain.length - 1
                                                "
                                                >→</span
                                            >
                                        </template>
                                    </div>

                                    <TripHubRouteStopsList
                                        :stops="trip.route.timeline"
                                        class="mt-3"
                                    />
                                </CardContent>
                            </Card>

                            <div
                                v-if="canShowRouteMap"
                                class="overflow-hidden rounded-2xl border border-border/70 shadow-lg shadow-teal-500/5"
                            >
                                <div class="aspect-[16/9] w-full">
                                    <TripRouteMap
                                        :points="trip.route.map_points"
                                    />
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent
                            v-if="trip.itinerary"
                            value="itinerary"
                            class="mt-6 space-y-4"
                        >
                            <p
                                v-if="trip.itinerary.summary"
                                class="text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ trip.itinerary.summary }}
                            </p>

                            <div class="space-y-4">
                                <Card
                                    v-for="day in trip.itinerary.days"
                                    :key="day.day"
                                    class="card-vibrant overflow-hidden"
                                >
                                    <div class="brand-gradient h-1.5" />
                                    <CardContent class="pt-4">
                                        <div class="flex items-center gap-2">
                                            <Badge variant="secondary"
                                                >Day {{ day.day }}</Badge
                                            >
                                            <span
                                                v-if="day.title"
                                                class="text-sm font-semibold"
                                            >
                                                {{ day.title }}
                                            </span>
                                            <span
                                                v-if="day.date"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    formatDisplayDate(day.date)
                                                }}
                                            </span>
                                        </div>
                                        <ul
                                            v-if="day.activities?.length"
                                            class="mt-3 space-y-2"
                                        >
                                            <li
                                                v-for="(
                                                    activity, index
                                                ) in day.activities"
                                                :key="index"
                                                class="flex gap-3 text-sm"
                                            >
                                                <span
                                                    v-if="activity.time"
                                                    class="w-14 shrink-0 font-medium text-muted-foreground"
                                                >
                                                    {{ activity.time }}
                                                </span>
                                                <div>
                                                    <p class="font-medium">
                                                        {{ activity.title }}
                                                    </p>
                                                    <p
                                                        v-if="activity.notes"
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        {{ activity.notes }}
                                                    </p>
                                                </div>
                                            </li>
                                        </ul>
                                    </CardContent>
                                </Card>
                            </div>

                            <div
                                v-if="trip.itinerary.packing_list.length"
                                class="flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    v-for="item in trip.itinerary.packing_list"
                                    :key="item"
                                    variant="outline"
                                >
                                    {{ item }}
                                </Badge>
                            </div>
                        </TabsContent>

                        <TabsContent value="join" class="mt-6">
                            <Card class="card-vibrant max-w-lg overflow-hidden">
                                <div class="brand-gradient h-1.5" />
                                <CardContent class="pt-6">
                                    <p
                                        v-if="!isAuthenticated()"
                                        class="text-sm text-muted-foreground"
                                    >
                                        <Link
                                            :href="login()"
                                            class="font-medium text-primary underline"
                                        >
                                            Log in
                                        </Link>
                                        to request to join this trip.
                                    </p>
                                    <p
                                        v-else-if="isOwner"
                                        class="text-sm text-muted-foreground"
                                    >
                                        This is your trip. Manage requests from
                                        the trip page.
                                    </p>
                                    <p
                                        v-else-if="isMember"
                                        class="text-sm text-muted-foreground"
                                    >
                                        You're already a member of this trip.
                                    </p>
                                    <div
                                        v-else-if="
                                            myJoinRequest?.status === 'accepted'
                                        "
                                        class="text-sm text-muted-foreground"
                                    >
                                        Your request was accepted.
                                    </div>
                                    <div
                                        v-else-if="myJoinRequest?.is_pending"
                                        class="space-y-3"
                                    >
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            Your request is pending review by
                                            the organizer.
                                        </p>
                                        <Form
                                            v-bind="
                                                withdrawJoinRequest.form([
                                                    trip.id,
                                                    myJoinRequest.id,
                                                ])
                                            "
                                            v-slot="{ processing }"
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                :disabled="processing"
                                            >
                                                <Spinner
                                                    v-if="processing"
                                                    class="mr-2"
                                                />
                                                Withdraw request
                                            </Button>
                                        </Form>
                                    </div>
                                    <p
                                        v-else-if="!trip.is_joinable"
                                        class="text-sm text-muted-foreground"
                                    >
                                        This trip is no longer accepting join
                                        requests.
                                    </p>
                                    <Form
                                        v-else
                                        v-bind="storeJoinRequest.form(trip.id)"
                                        v-slot="{
                                            errors,
                                            processing,
                                            recentlySuccessful,
                                        }"
                                        class="space-y-4"
                                    >
                                        <div class="grid gap-2">
                                            <Label for="travelers_count"
                                                >Number of travelers</Label
                                            >
                                            <Input
                                                id="travelers_count"
                                                name="travelers_count"
                                                type="number"
                                                min="1"
                                                default-value="1"
                                                required
                                            />
                                            <InputError
                                                :message="
                                                    errors.travelers_count
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="phone"
                                                >Phone number</Label
                                            >
                                            <Input
                                                id="phone"
                                                name="phone"
                                                type="tel"
                                                required
                                            />
                                            <InputError
                                                :message="errors.phone"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="message"
                                                >Message to the organizer</Label
                                            >
                                            <textarea
                                                id="message"
                                                name="message"
                                                rows="3"
                                                placeholder="Your experience, needs, or anything else the organizer should know."
                                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <InputError
                                                :message="errors.message"
                                            />
                                        </div>
                                        <Label
                                            class="flex items-start gap-2 text-sm font-normal"
                                        >
                                            <input
                                                type="checkbox"
                                                name="accepts_terms"
                                                value="1"
                                                required
                                                class="mt-1"
                                            />
                                            <span>
                                                I understand TripPilot only
                                                connects travelers — the
                                                organizer runs this trip, and
                                                any costs are settled directly
                                                with them.
                                            </span>
                                        </Label>
                                        <InputError
                                            :message="errors.accepts_terms"
                                        />
                                        <InputError :message="errors.join" />
                                        <Button
                                            type="submit"
                                            :disabled="processing"
                                        >
                                            <Spinner
                                                v-if="processing"
                                                class="mr-2"
                                            />
                                            Send join request
                                        </Button>
                                        <p
                                            v-if="recentlySuccessful"
                                            class="text-sm text-muted-foreground"
                                        >
                                            Request sent.
                                        </p>
                                    </Form>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="contact" class="mt-6">
                            <Card class="card-vibrant max-w-lg overflow-hidden">
                                <div class="brand-gradient h-1.5" />
                                <CardContent class="pt-6">
                                    <p
                                        v-if="!isAuthenticated()"
                                        class="text-sm text-muted-foreground"
                                    >
                                        <Link
                                            :href="login()"
                                            class="font-medium text-primary underline"
                                        >
                                            Log in
                                        </Link>
                                        to contact the organizer.
                                    </p>
                                    <p
                                        v-else-if="isOwner"
                                        class="text-sm text-muted-foreground"
                                    >
                                        This is your trip.
                                    </p>
                                    <Form
                                        v-else
                                        v-bind="storeInquiry.form(trip.id)"
                                        v-slot="{
                                            errors,
                                            processing,
                                            recentlySuccessful,
                                        }"
                                        class="space-y-4"
                                    >
                                        <div class="grid gap-2">
                                            <Label for="subject">Subject</Label>
                                            <Input
                                                id="subject"
                                                name="subject"
                                                required
                                            />
                                            <InputError
                                                :message="errors.subject"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="body">Message</Label>
                                            <textarea
                                                id="body"
                                                name="body"
                                                rows="4"
                                                required
                                                placeholder="Ask about gear, logistics, or anything else."
                                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <InputError
                                                :message="errors.body"
                                            />
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Your message stays inside TripPilot
                                            — the organizer won't see your email
                                            or phone number.
                                        </p>
                                        <Button
                                            type="submit"
                                            :disabled="processing"
                                        >
                                            <Spinner
                                                v-if="processing"
                                                class="mr-2"
                                            />
                                            Send message
                                        </Button>
                                        <p
                                            v-if="recentlySuccessful"
                                            class="text-sm text-muted-foreground"
                                        >
                                            Message sent.
                                        </p>
                                    </Form>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>
            </div>

            <div
                v-if="isAuthenticated() && !isOwner"
                class="mt-10 border-t pt-6"
            >
                <Dialog v-model:open="reportDialogOpen">
                    <DialogTrigger as-child>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground"
                        >
                            <Flag class="mr-2 size-4" />
                            Report this trip
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Report this trip</DialogTitle>
                        </DialogHeader>
                        <Form
                            v-bind="reportTrip.form(trip.id)"
                            v-slot="{ errors, processing, recentlySuccessful }"
                            class="space-y-4"
                            @success="reportDialogOpen = false"
                        >
                            <div class="grid gap-2">
                                <Label for="reason">Reason</Label>
                                <select
                                    id="reason"
                                    name="reason"
                                    required
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="spam">Spam</option>
                                    <option value="scam">Scam</option>
                                    <option value="inappropriate">
                                        Inappropriate content
                                    </option>
                                    <option value="other">Other</option>
                                </select>
                                <InputError :message="errors.reason" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="report_message"
                                    >Details (optional)</Label
                                >
                                <textarea
                                    id="report_message"
                                    name="message"
                                    rows="3"
                                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError :message="errors.message" />
                            </div>
                            <Button type="submit" :disabled="processing">
                                <Spinner v-if="processing" class="mr-2" />
                                Submit report
                            </Button>
                            <p
                                v-if="recentlySuccessful"
                                class="text-sm text-muted-foreground"
                            >
                                Thanks — our team will review this trip.
                            </p>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </main>

        <PublicFooter container-class="max-w-5xl" />
    </div>
</template>
