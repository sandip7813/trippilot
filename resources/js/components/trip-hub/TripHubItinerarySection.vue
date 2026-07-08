<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Clock,
    Sparkles,
    CalendarDays,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDisplayDate, formatWeekdayShort } from '@/lib/dates';
import { cn } from '@/lib/utils';
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
    if (!props.aiConfigured) {
        return 'Add GEMINI_API_KEY to your environment to enable AI generation.';
    }

    if (!locationLabel(props.trip.destination)) {
        return 'Set a destination on this trip before generating an itinerary.';
    }

    return hasItinerary.value
        ? 'Regenerate the full day-by-day plan and refresh the destination banner image.'
        : 'Generate a day-by-day plan and destination banner tailored to this trip.';
});

function dayTabDateLabel(day: (typeof days.value)[number]): string {
    if (day.date) {
        return formatDisplayDate(day.date);
    }

    return `Day ${day.day}`;
}

function dayTabSubtitle(day: (typeof days.value)[number]): string {
    const weekday = day.date ? formatWeekdayShort(day.date) : null;

    if (weekday) {
        return `${weekday} - Day ${day.day}`;
    }

    return `Day ${day.day}`;
}

function dayTooltipParts(day: (typeof days.value)[number]): {
    date: string;
    dayCount: string;
    title: string;
} {
    const date = day.date
        ? formatDisplayDate(day.date, { weekday: true })
        : dayTabDateLabel(day);

    return {
        date,
        dayCount: `Day ${day.day}`,
        title: day.title ?? `Day ${day.day}`,
    };
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
    <Card class="card-vibrant overflow-hidden">
        <div class="brand-gradient h-1.5 opacity-90" />
        <CardHeader
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <CardTitle class="text-lg font-bold">Itinerary</CardTitle>
                    <Badge
                        class="bg-violet-500/15 text-violet-700 dark:text-violet-300"
                        >AI suggested</Badge
                    >
                    <Badge v-if="hasItinerary" variant="outline">
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
                <FormSavingOverlay
                    :show="processing"
                    :message="
                        hasItinerary
                            ? 'Regenerating itinerary...'
                            : 'Generating itinerary...'
                    "
                />
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
                :message="
                    (page.props.errors as Record<string, string>).destination
                "
            />

            <p v-if="!hasItinerary" class="text-sm text-muted-foreground">
                No days planned yet. Generate a personalized itinerary with
                Gemini.
            </p>

            <p
                v-if="trip.itinerary?.summary"
                class="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm leading-relaxed"
            >
                {{ trip.itinerary.summary }}
            </p>

            <template v-if="hasItinerary && selectedDay">
                <div class="-mx-1 flex gap-2 overflow-x-auto pb-1">
                    <Tooltip v-for="(day, index) in days" :key="day.day">
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                :class="
                                    cn(
                                        'flex min-w-[6.75rem] shrink-0 flex-col items-center gap-1 rounded-lg border px-3 py-2.5 text-center transition-colors',
                                        index === selectedDayIndex
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-muted/30 hover:bg-accent',
                                    )
                                "
                                @click="selectDay(index)"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-sm leading-none font-semibold"
                                >
                                    <CalendarDays class="size-4 shrink-0" />
                                    {{ dayTabDateLabel(day) }}
                                </span>
                                <span
                                    :class="
                                        cn(
                                            'text-xs leading-tight',
                                            index === selectedDayIndex
                                                ? 'text-primary-foreground/90'
                                                : 'text-muted-foreground',
                                        )
                                    "
                                >
                                    {{ dayTabSubtitle(day) }}
                                </span>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="top" class="max-w-xs">
                            <div class="space-y-1 text-center text-sm">
                                <p>{{ dayTooltipParts(day).date }}</p>
                                <p class="font-medium">
                                    {{ dayTooltipParts(day).dayCount }}
                                </p>
                                <p>{{ dayTooltipParts(day).title }}</p>
                            </div>
                        </TooltipContent>
                    </Tooltip>
                </div>

                <div class="rounded-lg border border-border/60">
                    <div
                        class="flex items-center justify-between gap-3 border-b border-border/60 px-4 py-4"
                    >
                        <div class="space-y-1">
                            <h3 class="text-base leading-tight font-semibold">
                                Day {{ selectedDay.day }}
                                <span v-if="selectedDay.title"
                                    >— {{ selectedDay.title }}</span
                                >
                            </h3>
                            <p
                                v-if="selectedDay.date"
                                class="text-sm font-medium text-teal-700 dark:text-teal-300"
                            >
                                {{
                                    formatDisplayDate(selectedDay.date, {
                                        weekday: true,
                                    })
                                }}
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
                            <span
                                class="min-w-16 text-center text-xs text-muted-foreground"
                            >
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
                    <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                        No activities listed for this day.
                    </p>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
