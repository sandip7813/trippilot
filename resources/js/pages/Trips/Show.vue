<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    Heart,
    MapPin,
    Pencil,
    Trash2,
    Users,
    Wallet,
} from '@lucide/vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
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
import { edit, index as tripsIndex } from '@/routes/trips';
import type { Trip } from '@/types/trip';
import { locationLabel, locationRouteLabel } from '@/types/trip';

const { trip } = defineProps<{
    trip: Trip;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: tripsIndex() },
        ],
    },
});

function toggleFavorite(): void {
    router.patch(`/trips/${trip.id}/favorite`, {}, { preserveScroll: true });
}

function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
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
                    <p>{{ formatDate(trip.start_date) }}</p>
                    <p class="text-muted-foreground">to {{ formatDate(trip.end_date) }}</p>
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
            <CardHeader>
                <CardTitle class="text-base">Itinerary</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!trip.itinerary?.days?.length"
                    class="text-sm text-muted-foreground"
                >
                    No days planned yet. AI itinerary generation arrives in Phase 2 — for now you
                    can capture trip details above.
                </p>
                <div v-else class="space-y-4">
                    <div
                        v-for="day in trip.itinerary.days"
                        :key="day.day"
                        class="rounded-lg border border-border/60 p-4"
                    >
                        <h3 class="font-medium">Day {{ day.day }} — {{ day.title }}</h3>
                    </div>
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
