<script setup lang="ts">
import {
    CloudFog,
    CloudLightning,
    CloudRain,
    CloudSun,
    Snowflake,
    Sun,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { formatDisplayDate } from '@/lib/dates';
import type { TripWeatherDay, TripWeatherSegment } from '@/types/weather';

const props = defineProps<{
    segment: TripWeatherSegment;
    compact?: boolean;
}>();

const isUnavailable = computed(() => props.segment.available === false);

const forecastDays = computed(
    () => props.segment.forecast_days ?? props.segment.days ?? [],
);

const showTypicalStats = computed(
    () =>
        props.segment.mode === 'typical' &&
        props.segment.temperature_min != null &&
        props.segment.temperature_max != null,
);

const showForecast = computed(
    () =>
        forecastDays.value.length > 0 &&
        (props.segment.mode === 'forecast' || props.segment.mode === 'mixed'),
);

const showMixedRemainder = computed(
    () =>
        props.segment.mode === 'mixed' &&
        (props.segment.typical_remainder ||
            props.segment.remainder_period_label),
);

function weatherIcon(day: TripWeatherDay) {
    return (
        {
            clear: Sun,
            cloudy: CloudSun,
            fog: CloudFog,
            rain: CloudRain,
            snow: Snowflake,
            storm: CloudLightning,
        }[day.weather_kind] ?? CloudSun
    );
}

function nightsLabel(nights: number | null | undefined): string | null {
    if (nights == null || nights <= 0) {
        return null;
    }

    return `${nights} night${nights === 1 ? '' : 's'}`;
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <p v-if="!compact && segment.segment_label" class="font-medium">
                <span
                    v-if="segment.sequence"
                    class="mr-1.5 text-muted-foreground"
                >
                    {{ segment.sequence }}.
                </span>
                {{ segment.segment_label }}
            </p>
            <p
                v-else-if="compact && segment.segment_label"
                class="text-sm font-medium text-muted-foreground"
            >
                {{ segment.segment_label }}
            </p>
            <Badge
                v-if="segment.date_from && segment.date_to"
                variant="outline"
            >
                {{ formatDisplayDate(segment.date_from) }} –
                {{ formatDisplayDate(segment.date_to) }}
            </Badge>
            <Badge v-if="nightsLabel(segment.nights)" variant="secondary">
                {{ nightsLabel(segment.nights) }}
            </Badge>
            <Badge v-if="segment.mode_label" variant="outline">
                {{ segment.mode_label }}
            </Badge>
        </div>

        <p v-if="isUnavailable" class="text-sm text-muted-foreground">
            {{
                segment.message ?? 'Weather is not available for this stop yet.'
            }}
        </p>

        <template v-else>
            <p v-if="segment.summary" class="text-sm font-medium">
                {{ segment.summary }}
            </p>

            <div v-if="showTypicalStats" class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">Typical range</p>
                    <p class="mt-1 text-lg font-semibold">
                        {{ segment.temperature_min }}–{{
                            segment.temperature_max
                        }}°C
                    </p>
                </div>
                <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">Avg daily rain</p>
                    <p class="mt-1 text-lg font-semibold">
                        {{ segment.avg_daily_precipitation_mm }} mm
                    </p>
                </div>
                <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                    <p class="text-xs text-muted-foreground">Rainy days</p>
                    <p class="mt-1 text-lg font-semibold">
                        ~{{ segment.rainy_day_percent }}%
                    </p>
                </div>
            </div>

            <div v-if="showForecast" class="space-y-2">
                <p class="text-xs font-medium text-muted-foreground">
                    Live forecast
                    <span v-if="segment.forecast_range_label">
                        · {{ segment.forecast_range_label }}</span
                    >
                </p>
                <div class="-mx-1 flex gap-2 overflow-x-auto pb-1">
                    <div
                        v-for="day in forecastDays"
                        :key="day.date"
                        class="min-w-24 shrink-0 rounded-lg border border-border/60 bg-muted/20 p-3 text-center"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{
                                formatDisplayDate(day.date, {
                                    weekday: true,
                                })
                            }}
                        </p>
                        <component
                            :is="weatherIcon(day)"
                            class="mx-auto my-2 size-5 text-muted-foreground"
                        />
                        <p class="text-sm font-medium">
                            {{ day.temperature_max }}°
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ day.temperature_min }}° ·
                            {{ day.precipitation_mm }} mm
                        </p>
                        <p
                            v-if="day.weather_label"
                            class="mt-1 truncate text-[10px] text-muted-foreground"
                        >
                            {{ day.weather_label }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="showMixedRemainder"
                class="space-y-3 rounded-lg border border-dashed border-border/80 bg-muted/10 p-4"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    Seasonal outlook for the rest of this stop ·
                    {{
                        segment.typical_remainder?.period_label ??
                        segment.remainder_period_label
                    }}
                </p>
                <template v-if="segment.typical_remainder">
                    <p class="text-sm">
                        {{ segment.typical_remainder.summary }}
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Typical range
                            </p>
                            <p class="font-medium">
                                {{
                                    segment.typical_remainder.temperature_min
                                }}–{{
                                    segment.typical_remainder.temperature_max
                                }}°C
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Avg daily rain
                            </p>
                            <p class="font-medium">
                                {{
                                    segment.typical_remainder
                                        .avg_daily_precipitation_mm
                                }}
                                mm
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Rainy days
                            </p>
                            <p class="font-medium">
                                ~{{
                                    segment.typical_remainder.rainy_day_percent
                                }}%
                            </p>
                        </div>
                    </div>
                </template>
                <p v-else class="text-sm text-muted-foreground">
                    Historical averages for this period are temporarily
                    unavailable. Refresh the page in a few minutes.
                </p>
            </div>

            <p
                v-if="segment.disclaimer"
                class="text-xs leading-relaxed text-muted-foreground"
            >
                {{ segment.disclaimer }}
            </p>
        </template>
    </div>
</template>
