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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDisplayDate } from '@/lib/dates';
import type { TripWeather, TripWeatherDay } from '@/types/weather';

const props = defineProps<{
    weather: TripWeather | null;
}>();

const isUnavailable = computed(
    () => props.weather === null || props.weather.available === false,
);

const forecastDays = computed(
    () => props.weather?.forecast_days ?? props.weather?.days ?? [],
);

function weatherIcon(day: TripWeatherDay) {
    return {
        clear: Sun,
        cloudy: CloudSun,
        fog: CloudFog,
        rain: CloudRain,
        snow: Snowflake,
        storm: CloudLightning,
    }[day.weather_kind] ?? CloudSun;
}
</script>

<template>
    <Card class="card-vibrant h-full overflow-hidden">
        <div class="h-1.5 bg-gradient-to-r from-sky-400 via-cyan-500 to-indigo-500" />
        <CardHeader class="flex flex-row flex-wrap items-start justify-between gap-3 pb-2">
            <div>
                <CardTitle class="flex items-center gap-2 text-lg font-bold">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400">
                        <CloudSun class="size-4" />
                    </span>
                    Weather
                </CardTitle>
                <p
                    v-if="weather?.location_label"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ weather.location_label }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Badge
                    v-if="weather?.mode_label"
                    variant="outline"
                >
                    {{ weather.mode_label }}
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <p
                v-if="isUnavailable"
                class="text-sm text-muted-foreground"
            >
                {{ weather?.message ?? 'Weather is not available for this trip yet.' }}
            </p>

            <template v-else-if="weather">
                <p class="text-sm font-medium">
                    {{ weather.summary }}
                </p>

                <div
                    v-if="weather.mode === 'typical'"
                    class="grid gap-3 sm:grid-cols-3"
                >
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">Typical range</p>
                        <p class="mt-1 text-lg font-semibold">
                            {{ weather.temperature_min }}–{{ weather.temperature_max }}°C
                        </p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">Avg daily rain</p>
                        <p class="mt-1 text-lg font-semibold">
                            {{ weather.avg_daily_precipitation_mm }} mm
                        </p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">Rainy days</p>
                        <p class="mt-1 text-lg font-semibold">
                            ~{{ weather.rainy_day_percent }}%
                        </p>
                    </div>
                </div>

                <div
                    v-if="forecastDays.length && (weather.mode === 'forecast' || weather.mode === 'mixed')"
                    class="space-y-2"
                >
                    <p class="text-xs font-medium text-muted-foreground">
                        Live forecast
                        <span v-if="weather.forecast_range_label"> · {{ weather.forecast_range_label }}</span>
                    </p>
                    <div class="-mx-1 flex gap-2 overflow-x-auto pb-1">
                        <div
                            v-for="day in forecastDays"
                            :key="day.date"
                            class="min-w-24 shrink-0 rounded-lg border border-border/60 bg-muted/20 p-3 text-center"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ formatDisplayDate(day.date, { weekday: true }) }}
                            </p>
                            <component
                                :is="weatherIcon(day)"
                                class="mx-auto my-2 size-5 text-muted-foreground"
                            />
                            <p class="text-sm font-medium">
                                {{ day.temperature_max }}°
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ day.temperature_min }}° · {{ day.precipitation_mm }} mm
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="weather.mode === 'mixed' && (weather.typical_remainder || weather.remainder_period_label)"
                    class="space-y-3 rounded-lg border border-dashed border-border/80 bg-muted/10 p-4"
                >
                    <p class="text-xs font-medium text-muted-foreground">
                        Seasonal outlook for the rest of your trip
                        · {{ weather.typical_remainder?.period_label ?? weather.remainder_period_label }}
                    </p>
                    <template v-if="weather.typical_remainder">
                        <p class="text-sm">
                            {{ weather.typical_remainder.summary }}
                        </p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-xs text-muted-foreground">Typical range</p>
                                <p class="font-medium">
                                    {{ weather.typical_remainder.temperature_min }}–{{ weather.typical_remainder.temperature_max }}°C
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Avg daily rain</p>
                                <p class="font-medium">{{ weather.typical_remainder.avg_daily_precipitation_mm }} mm</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Rainy days</p>
                                <p class="font-medium">~{{ weather.typical_remainder.rainy_day_percent }}%</p>
                            </div>
                        </div>
                    </template>
                    <p
                        v-else
                        class="text-sm text-muted-foreground"
                    >
                        Historical averages for this period are temporarily unavailable. Refresh the page in a few minutes.
                    </p>
                </div>

                <p
                    v-if="weather.disclaimer"
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    {{ weather.disclaimer }}
                </p>
            </template>
        </CardContent>
    </Card>
</template>
