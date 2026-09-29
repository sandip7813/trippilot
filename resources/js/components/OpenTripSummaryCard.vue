<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Calendar, MapPin } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDisplayDateRange } from '@/lib/dates';

export type OpenTripSummary = {
    id: string;
    title: string;
    type_label: string;
    destination: { label: string } | null;
    start_date: string | null;
    end_date: string | null;
    cover_image_thumb_url: string | null;
    organizer: { name: string } | null;
};

defineProps<{
    trip: OpenTripSummary;
    href: string;
}>();
</script>

<template>
    <Card class="card-vibrant h-full overflow-hidden transition hover:shadow-lg">
        <div
            class="h-28 w-full bg-cover bg-center brand-gradient"
            :style="trip.cover_image_thumb_url
                ? { backgroundImage: `url(${trip.cover_image_thumb_url})` }
                : undefined"
        />
        <div class="flex flex-col gap-2 p-4">
            <div class="flex items-center justify-between gap-2">
                <Badge variant="outline" class="text-xs">{{ trip.type_label }}</Badge>
                <slot name="badge" />
            </div>
            <h3 class="truncate text-sm font-semibold leading-snug">
                <Link :href="href" class="transition-colors hover:text-primary">
                    {{ trip.title }}
                </Link>
            </h3>
            <p
                v-if="trip.destination"
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <MapPin class="size-3.5 shrink-0" />
                <span class="truncate">{{ trip.destination.label }}</span>
            </p>
            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Calendar class="size-3.5 shrink-0" />
                <span class="truncate">{{
                    formatDisplayDateRange(trip.start_date, trip.end_date)
                }}</span>
            </p>
            <p v-if="trip.organizer" class="truncate text-xs text-muted-foreground">
                By {{ trip.organizer.name }}
            </p>
            <slot name="meta" />
            <Button size="sm" as-child class="mt-1">
                <Link :href="href">View trip</Link>
            </Button>
        </div>
    </Card>
</template>
