<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Heart,
    Maximize2,
    Minimize2,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import LocationCoordinatesAlert from '@/components/LocationCoordinatesAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import TripHubAtAGlance from '@/components/trip-hub/TripHubAtAGlance.vue';
import TripHubItinerarySection from '@/components/trip-hub/TripHubItinerarySection.vue';
import TripHubPracticalSection from '@/components/trip-hub/TripHubPracticalSection.vue';
import TripHubUsefulLinks from '@/components/trip-hub/TripHubUsefulLinks.vue';
import TripWeatherCard from '@/components/TripWeatherCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { edit, index as tripsIndex } from '@/routes/trips';
import { normalizeBudgetBreakdown } from '@/lib/budget';
import type { Trip } from '@/types/trip';
import type { TripWeather } from '@/types/weather';
import { locationHasCoordinates, locationRouteLabel } from '@/types/trip';
import { cn } from '@/lib/utils';

const props = defineProps<{
    trip: Trip;
    aiConfigured: boolean;
    weather: TripWeather | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: tripsIndex() },
        ],
    },
});

const needsDestinationCoordinates = computed(
    () => Boolean(props.trip.destination?.label) && ! locationHasCoordinates(props.trip.destination),
);

const page = usePage();

const appCurrency = computed(
    () => (page.props.currency as { code?: string } | undefined)?.code ?? 'INR',
);

const hasBudgetBreakdown = computed(() => {
    if ((props.trip.itinerary?.days?.length ?? 0) === 0) {
        return false;
    }

    const budget = normalizeBudgetBreakdown(
        props.trip.itinerary?.budget_breakdown as Record<string, unknown> | undefined,
        appCurrency.value,
    );

    return budget.hasLineItems || budget.estimatedTotal !== null;
});

const hasExtras = computed(
    () => Boolean(props.trip.notes)
        || (props.trip.itinerary?.packing_list?.length ?? 0) > 0
        || hasBudgetBreakdown.value,
);

function toggleFavorite(): void {
    router.patch(`/trips/${props.trip.id}/favorite`, {}, { preserveScroll: true });
}

const bannerExpanded = ref(false);
</script>

<template>
    <Head :title="trip.title" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <div
            v-if="trip.cover_image_url"
            class="relative -mx-4 -mt-4 overflow-hidden rounded-b-2xl shadow-lg md:-mx-6 md:-mt-6"
            :class="bannerExpanded ? 'bg-muted/30' : ''"
        >
            <div
                :class="cn(
                    'w-full overflow-hidden',
                    bannerExpanded
                        ? 'aspect-[21/9] min-h-[13rem] sm:min-h-[16rem] lg:min-h-[20rem]'
                        : 'h-40 md:h-52',
                )"
            >
                <img
                    :src="trip.cover_image_url"
                    :alt="`${trip.title} destination banner`"
                    width="1920"
                    height="900"
                    fetchpriority="high"
                    decoding="async"
                    :class="cn(
                        'size-full',
                        bannerExpanded ? 'object-contain object-center' : 'object-cover',
                    )"
                />
            </div>
            <Button
                type="button"
                variant="secondary"
                size="icon"
                class="absolute top-4 right-4 z-10 size-8 border-border/60 bg-background/85 shadow-sm backdrop-blur-sm"
                :title="bannerExpanded ? 'Collapse banner' : 'Expand banner'"
                @click="bannerExpanded = !bannerExpanded"
            >
                <Minimize2 v-if="bannerExpanded" class="size-4" />
                <Maximize2 v-else class="size-4" />
            </Button>
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-background/90 via-background/20 to-transparent" />
            <div class="absolute inset-x-0 bottom-0 p-4 md:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            {{ trip.title }}
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ locationRouteLabel(trip.origin, trip.destination) }}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        as-child
                        class="shrink-0 border-border/60 bg-background/85 shadow-sm backdrop-blur-sm"
                    >
                        <Link :href="tripsIndex()">
                            <ArrowLeft class="mr-2 size-4" />
                            Back to trips
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <PageHeader
            v-if="!trip.cover_image_url"
            :title="trip.title"
            :description="locationRouteLabel(trip.origin, trip.destination)"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="tripsIndex()">
                            <ArrowLeft class="mr-2 size-4" />
                            Back to trips
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        :title="trip.is_favorite ? 'Remove from favorites' : 'Add to favorites'"
                        :class="trip.is_favorite ? 'text-rose-500' : ''"
                        @click="toggleFavorite"
                    >
                        <Heart class="size-4" :class="trip.is_favorite ? 'fill-current' : ''" />
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="edit(trip.id)">
                            <Pencil class="mr-2 size-4" />
                            Edit
                        </Link>
                    </Button>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="destructive">
                                <Trash2 class="mr-2 size-4" />
                                Delete
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Delete this trip?</DialogTitle>
                                <DialogDescription>
                                    This will permanently remove "{{ trip.title }}" and its itinerary.
                                    This action cannot be undone.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <DialogClose as-child>
                                    <Button variant="outline">Cancel</Button>
                                </DialogClose>
                                <Form
                                    v-bind="TripController.destroy.form(trip.id)"
                                    v-slot="{ processing }"
                                >
                                    <FormSavingOverlay
                                        :show="processing"
                                        message="Deleting trip..."
                                    />
                                    <Button type="submit" variant="destructive" :disabled="processing">
                                        Delete trip
                                    </Button>
                                </Form>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </template>
        </PageHeader>

        <div
            v-else
            class="flex flex-wrap items-center justify-end gap-2"
        >
            <Button
                variant="outline"
                size="icon"
                :title="trip.is_favorite ? 'Remove from favorites' : 'Add to favorites'"
                :class="trip.is_favorite ? 'text-rose-500' : ''"
                @click="toggleFavorite"
            >
                <Heart class="size-4" :class="trip.is_favorite ? 'fill-current' : ''" />
            </Button>
            <Button variant="outline" as-child>
                <Link :href="edit(trip.id)">
                    <Pencil class="mr-2 size-4" />
                    Edit
                </Link>
            </Button>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive">
                        <Trash2 class="mr-2 size-4" />
                        Delete
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this trip?</DialogTitle>
                        <DialogDescription>
                            This will permanently remove "{{ trip.title }}" and its itinerary.
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Form
                            v-bind="TripController.destroy.form(trip.id)"
                            v-slot="{ processing }"
                        >
                            <FormSavingOverlay
                                :show="processing"
                                message="Deleting trip..."
                            />
                            <Button type="submit" variant="destructive" :disabled="processing">
                                Delete trip
                            </Button>
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <div class="flex flex-wrap gap-2">
            <Badge class="bg-teal-500/15 text-teal-800 hover:bg-teal-500/20 dark:text-teal-200">{{ trip.status_label }}</Badge>
            <Badge variant="outline" class="border-violet-500/30 bg-violet-500/5">{{ trip.type_label }}</Badge>
            <Badge
                v-if="trip.travel_style_label"
                variant="secondary"
                class="bg-amber-500/15 text-amber-800 dark:text-amber-200"
            >
                {{ trip.travel_style_label }}
            </Badge>
            <Badge
                v-if="trip.trip_scope_label"
                variant="outline"
                class="border-sky-500/30 bg-sky-500/5"
            >
                {{ trip.trip_scope_label }}
            </Badge>
        </div>

        <LocationCoordinatesAlert
            v-if="needsDestinationCoordinates"
            :edit-url="edit(trip.id)"
        />

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="space-y-4 xl:col-span-2">
                <h2 class="section-heading">At a glance</h2>
                <TripHubAtAGlance :trip="trip" />
            </div>

            <div class="xl:col-span-3">
                <TripWeatherCard :weather="weather" />
            </div>
        </div>

        <TripHubItinerarySection
            :trip="trip"
            :ai-configured="aiConfigured"
        />

        <section
            v-if="hasExtras"
            class="space-y-4"
        >
            <h2 class="section-heading">Notes &amp; budget</h2>
            <TripHubPracticalSection :trip="trip" />
        </section>

        <TripHubUsefulLinks :trip="trip" />
    </div>
</template>
