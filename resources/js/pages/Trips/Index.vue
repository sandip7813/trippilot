<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    Heart,
    MapPin,
    MapPinned,
    Pencil,
    Plus,
    Trash2,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import EmptyState from '@/components/EmptyState.vue';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { tripCardAccent } from '@/lib/card-accents';
import { formatDisplayDateRange } from '@/lib/dates';
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

const deleteDialogOpen = ref(false);
const tripToDelete = ref<Trip | null>(null);

function setFilter(filter: TripFilter): void {
    router.get(
        tripsIndex({ query: { filter } }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function toggleFavorite(trip: Trip): void {
    router.patch(`/trips/${trip.id}/favorite`, {}, { preserveScroll: true });
}

function openDeleteDialog(trip: Trip): void {
    tripToDelete.value = trip;
    deleteDialogOpen.value = true;
}

function closeDeleteDialog(): void {
    deleteDialogOpen.value = false;
    tripToDelete.value = null;
}

function statusVariant(
    status: Trip['status'],
): 'default' | 'secondary' | 'outline' {
    if (status === 'planned') {
        return 'default';
    }

    if (status === 'archived') {
        return 'outline';
    }

    return 'secondary';
}

function coverThumbUrl(trip: Trip): string | null {
    return trip.cover_image_thumb_url ?? trip.cover_image_url ?? null;
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
                :class="
                    props.filter === item.key
                        ? 'shadow-md shadow-teal-500/20'
                        : 'border-border/70 bg-card/60'
                "
                @click="setFilter(item.key)"
            >
                {{ item.label }}
                <span class="ml-1.5 text-xs opacity-70"
                    >({{ counts[item.key] }})</span
                >
            </Button>
        </div>

        <EmptyState
            v-if="trips.length === 0"
            :title="
                filter === 'favorites'
                    ? 'No favorite trips'
                    : filter === 'archived'
                      ? 'No archived trips'
                      : 'No trips yet'
            "
            :description="
                filter === 'all'
                    ? 'Create your first trip to start building an itinerary. Then generate a plan with AI.'
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
                v-for="(trip, index) in trips"
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
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h3
                                    class="truncate text-base leading-tight font-semibold"
                                >
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
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :title="
                                    trip.is_favorite
                                        ? 'Remove from favorites'
                                        : 'Add to favorites'
                                "
                                :class="
                                    trip.is_favorite
                                        ? 'text-rose-500 hover:bg-rose-500/10 hover:text-rose-600'
                                        : 'text-muted-foreground'
                                "
                                @click="toggleFavorite(trip)"
                            >
                                <Heart
                                    class="size-4"
                                    :class="
                                        trip.is_favorite ? 'fill-current' : ''
                                    "
                                />
                            </Button>
                        </div>

                        <div class="mt-2.5 flex flex-wrap gap-1.5">
                            <Badge
                                :variant="statusVariant(trip.status)"
                                class="text-xs"
                                >{{ trip.status_label }}</Badge
                            >
                            <Badge variant="outline" class="text-xs">{{
                                trip.type_label
                            }}</Badge>
                            <Badge
                                v-if="trip.travel_style_label"
                                variant="secondary"
                                class="bg-violet-500/10 text-xs text-violet-700 dark:text-violet-300"
                            >
                                {{ trip.travel_style_label }}
                            </Badge>
                        </div>

                        <div
                            class="mt-2.5 space-y-1 text-sm text-muted-foreground"
                        >
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
                        <Button
                            variant="outline"
                            size="sm"
                            as-child
                            class="shrink-0"
                        >
                            <Link :href="edit(trip.id)">
                                <Pencil class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            :title="`Delete ${trip.title}`"
                            @click="openDeleteDialog(trip)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </Card>
        </div>

        <Dialog
            v-model:open="deleteDialogOpen"
            @update:open="
                (open) => {
                    if (!open) tripToDelete = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete this trip?</DialogTitle>
                    <DialogDescription>
                        <template v-if="tripToDelete">
                            This will permanently remove "{{
                                tripToDelete.title
                            }}" and its itinerary. This action cannot be undone.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Form
                        v-if="tripToDelete"
                        v-bind="TripController.destroy.form(tripToDelete.id)"
                        v-slot="{ processing }"
                        @success="closeDeleteDialog"
                    >
                        <FormSavingOverlay
                            :show="processing"
                            message="Deleting trip..."
                        />
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            Delete trip
                        </Button>
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
