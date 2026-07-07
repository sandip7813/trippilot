<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    Clock,
    Heart,
    MapPin,
    Pencil,
    Sparkles,
    Trash2,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Spinner } from '@/components/ui/spinner';
import { edit, index as tripsIndex } from '@/routes/trips';
import { formatDisplayDate } from '@/lib/dates';
import type { Trip } from '@/types/trip';
import { locationLabel, locationRouteLabel } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
    aiConfigured: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: tripsIndex() },
        ],
    },
});

const page = usePage();

const hasItinerary = computed(() => (props.trip.itinerary?.days?.length ?? 0) > 0);

const canGenerate = computed(
    () => props.aiConfigured && Boolean(locationLabel(props.trip.destination)),
);

const generateHint = computed((): string => {
    if (!props.aiConfigured) {
        return 'Add GEMINI_API_KEY to your environment to enable AI generation.';
    }

    if (!locationLabel(props.trip.destination)) {
        return 'Set a destination on this trip before generating an itinerary.';
    }

    return hasItinerary.value
        ? 'Regenerate the full day-by-day plan.'
        : 'Generate a day-by-day plan tailored to this trip.';
});

function toggleFavorite(): void {
    router.patch(`/trips/${props.trip.id}/favorite`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="trip.title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
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
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Calendar class="size-4" />
                        Dates
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm">
                    <p>{{ formatDisplayDate(trip.start_date, { weekday: true }) }}</p>
                    <p class="text-muted-foreground">to {{ formatDisplayDate(trip.end_date, { weekday: true }) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Users class="size-4" />
                        Travelers
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{ trip.travelers }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Wallet class="size-4" />
                        Budget
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">
                    {{ trip.budget != null ? `$${trip.budget.toLocaleString()}` : '—' }}
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <MapPin class="size-4" />
                        Route
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 text-sm">
                    <p v-if="locationLabel(trip.origin)">
                        <span class="text-muted-foreground">From:</span>
                        {{ locationLabel(trip.origin) }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">To:</span>
                        {{ locationLabel(trip.destination) ?? 'Not set' }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card v-if="trip.notes">
            <CardHeader>
                <CardTitle class="text-base">Notes</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground">
                    {{ trip.notes }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle class="text-base">Itinerary</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">{{ generateHint }}</p>
                </div>
                <Form
                    v-bind="TripController.generateItinerary.form(trip.id)"
                    v-slot="{ processing }"
                >
                    <Button type="submit" :disabled="!canGenerate || processing">
                        <Spinner v-if="processing" class="mr-2" />
                        <Sparkles v-else class="mr-2 size-4" />
                        {{ hasItinerary ? 'Regenerate' : 'Generate with AI' }}
                    </Button>
                </Form>
            </CardHeader>
            <CardContent class="space-y-4">
                <InputError
                    :message="(page.props.errors as Record<string, string>).ai"
                />
                <InputError
                    :message="(page.props.errors as Record<string, string>).destination"
                />

                <p
                    v-if="!hasItinerary"
                    class="text-sm text-muted-foreground"
                >
                    No days planned yet. Click generate to build a personalized itinerary with
                    Gemini.
                </p>

                <p
                    v-if="trip.itinerary?.summary"
                    class="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm leading-relaxed"
                >
                    {{ trip.itinerary.summary }}
                </p>

                <div v-if="hasItinerary" class="space-y-4">
                    <div
                        v-for="day in trip.itinerary.days"
                        :key="day.day"
                        class="rounded-lg border border-border/60 p-4"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="font-medium">
                                Day {{ day.day }}
                                <span v-if="day.title">— {{ day.title }}</span>
                            </h3>
                            <span v-if="day.date" class="text-xs text-muted-foreground">
                                {{ formatDisplayDate(day.date, { weekday: true }) }}
                            </span>
                        </div>

                        <ul v-if="day.activities?.length" class="mt-4 space-y-3">
                            <li
                                v-for="(activity, index) in day.activities"
                                :key="`${day.day}-${index}`"
                                class="flex gap-3 text-sm"
                            >
                                <Clock
                                    v-if="activity.time"
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p class="font-medium">
                                        <span
                                            v-if="activity.time"
                                            class="mr-2 text-muted-foreground"
                                        >
                                            {{ activity.time }}
                                        </span>
                                        {{ activity.title }}
                                    </p>
                                    <p
                                        v-if="activity.notes"
                                        class="mt-1 text-muted-foreground"
                                    >
                                        {{ activity.notes }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    v-if="trip.itinerary?.packing_list?.length"
                    class="rounded-lg border border-border/60 p-4"
                >
                    <h3 class="text-sm font-medium">Packing list</h3>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                        <li v-for="item in trip.itinerary.packing_list" :key="item">
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>

        <Button variant="ghost" as-child class="self-start">
            <Link :href="tripsIndex()">
                <ArrowLeft class="mr-2 size-4" />
                Back to trips
            </Link>
        </Button>
    </div>
</template>
