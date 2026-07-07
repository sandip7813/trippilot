<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Heart,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
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
</script>

<template>
    <Head :title="trip.title" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <PageHeader
            :title="trip.title"
            :description="locationRouteLabel(trip.origin, trip.destination)"
        >
            <template #actions>
                <Button
                    variant="outline"
                    size="icon"
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
                            <Form v-bind="TripController.destroy.form(trip.id)">
                                <Button type="submit" variant="destructive">Delete trip</Button>
                            </Form>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2">
            <Badge>{{ trip.status_label }}</Badge>
            <Badge variant="outline">{{ trip.type_label }}</Badge>
            <Badge v-if="trip.travel_style_label" variant="secondary">
                {{ trip.travel_style_label }}
            </Badge>
            <Badge v-if="trip.trip_scope_label" variant="outline">
                {{ trip.trip_scope_label }}
            </Badge>
        </div>

        <LocationCoordinatesAlert
            v-if="needsDestinationCoordinates"
            :edit-url="edit(trip.id)"
        />

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="space-y-4 xl:col-span-2">
                <h2 class="text-lg font-semibold tracking-tight">At a glance</h2>
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
            <h2 class="text-lg font-semibold tracking-tight">Notes &amp; budget</h2>
            <TripHubPracticalSection :trip="trip" />
        </section>

        <TripHubUsefulLinks :trip="trip" />

        <Button variant="ghost" as-child class="self-start">
            <Link :href="tripsIndex()">
                <ArrowLeft class="mr-2 size-4" />
                Back to trips
            </Link>
        </Button>
    </div>
</template>
