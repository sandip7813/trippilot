<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Clock, Sparkles } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { formatDisplayDate } from '@/lib/dates';
import type { Trip } from '@/types/trip';
import { locationLabel } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
    aiConfigured: boolean;
}>();

const page = usePage();
const selectedDayIndex = ref(0);

const days = computed(() => props.trip.itinerary?.days ?? []);
const hasItinerary = computed(() => days.value.length > 0);
const selectedDay = computed(() => days.value[selectedDayIndex.value] ?? null);

watch(days, (nextDays) => {
    if (selectedDayIndex.value >= nextDays.length) {
        selectedDayIndex.value = Math.max(0, nextDays.length - 1);
    }
});

const canGenerate = computed(
    () => props.aiConfigured && Boolean(locationLabel(props.trip.destination)),
);

const generateHint = computed((): string => {
    if (! props.aiConfigured) {
        return 'Add GEMINI_API_KEY to your environment to enable AI generation.';
    }

    if (! locationLabel(props.trip.destination)) {
        return 'Set a destination on this trip before generating an itinerary.';
    }

    return hasItinerary.value
        ? 'Regenerate the full day-by-day plan.'
        : 'Generate a day-by-day plan tailored to this trip.';
});

function dayTabLabel(day: (typeof days.value)[number]): string {
    if (day.date) {
        return formatDisplayDate(day.date);
    }

    return `Day ${day.day}`;
}

function selectDay(index: number): void {
    selectedDayIndex.value = index;
}

function previousDay(): void {
    if (selectedDayIndex.value > 0) {
        selectedDayIndex.value -= 1;
    }
}

function nextDay(): void {
    if (selectedDayIndex.value < days.value.length - 1) {
        selectedDayIndex.value += 1;
    }
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <CardTitle class="text-base">Itinerary</CardTitle>
                    <Badge variant="secondary">AI suggested</Badge>
                    <Badge
                        v-if="hasItinerary"
                        variant="outline"
                    >
                        {{ days.length }} days
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ generateHint }}
                </p>
            </div>
            <Form
                v-bind="TripController.generateItinerary.form(trip.id)"
                v-slot="{ processing }"
                class="shrink-0"
            >
                <Button
                    type="submit"
                    :disabled="!canGenerate || processing"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    <Sparkles v-else class="mr-2 size-4" />
                    {{ hasItinerary ? 'Regenerate' : 'Generate with AI' }}
                </Button>
            </Form>
        </CardHeader>

        <CardContent class="space-y-4">
            <InputError :message="(page.props.errors as Record<string, string>).ai" />
            <InputError :message="(page.props.errors as Record<string, string>).destination" />

            <p
                v-if="!hasItinerary"
                class="text-sm text-muted-foreground"
            >
                No days planned yet. Generate a personalized itinerary with Gemini.
            </p>

            <p
                v-if="trip.itinerary?.summary"
                class="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm leading-relaxed"
            >
                {{ trip.itinerary.summary }}
            </p>

            <template v-if="hasItinerary && selectedDay">
                <div class="-mx-1 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="(day, index) in days"
                        :key="day.day"
                        type="button"
                        :class="cn(
                            'shrink-0 rounded-full border px-3 py-1.5 text-left text-xs transition-colors',
                            index === selectedDayIndex
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-muted/30 hover:bg-accent',
                        )"
                        @click="selectDay(index)"
                    >
                        <span class="block font-medium">{{ dayTabLabel(day) }}</span>
                        <span
                            :class="cn(
                                'block truncate max-w-28',
                                index === selectedDayIndex ? 'text-primary-foreground/80' : 'text-muted-foreground',
                            )"
                        >
                            {{ day.title ?? `Day ${day.day}` }}
                        </span>
                    </button>
                </div>

                <div class="rounded-lg border border-border/60">
                    <div class="flex items-center justify-between gap-3 border-b border-border/60 px-4 py-3">
                        <div>
                            <h3 class="font-medium">
                                Day {{ selectedDay.day }}
                                <span v-if="selectedDay.title">— {{ selectedDay.title }}</span>
                            </h3>
                            <p
                                v-if="selectedDay.date"
                                class="text-xs text-muted-foreground"
                            >
                                {{ formatDisplayDate(selectedDay.date, { weekday: true }) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :disabled="selectedDayIndex === 0"
                                @click="previousDay"
                            >
                                <ChevronLeft class="size-4" />
                            </Button>
                            <span class="min-w-16 text-center text-xs text-muted-foreground">
                                {{ selectedDayIndex + 1 }} / {{ days.length }}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :disabled="selectedDayIndex >= days.length - 1"
                                @click="nextDay"
                            >
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <ul
                        v-if="selectedDay.activities?.length"
                        class="divide-y divide-border/60"
                    >
                        <li
                            v-for="(activity, index) in selectedDay.activities"
                            :key="`${selectedDay.day}-${index}`"
                            class="flex gap-3 px-4 py-3 text-sm"
                        >
                            <Clock
                                v-if="activity.time"
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <div class="min-w-0 flex-1">
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
                    <p
                        v-else
                        class="px-4 py-6 text-sm text-muted-foreground"
                    >
                        No activities listed for this day.
                    </p>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
