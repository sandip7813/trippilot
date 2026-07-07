<script setup lang="ts">
import {
    Cloud,
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

function weatherIcon(day: TripWeatherDay) {
    return {
        clear: Sun,
        cloudy: CloudSun,
        fog: CloudFog,
        rain: CloudRain,
        snow: Snowflake,
        storm: CloudLightning,
    }[day.weather_kind] ?? Cloud;
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-start justify-between gap-4 pb-2">
            <div>
                <CardTitle class="flex items-center gap-2 text-base">
                    <CloudSun class="size-4 text-muted-foreground" />
                    Weather
                </CardTitle>
                <p
                    v-if="weather.location_label"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ weather.location_label }}
                </p>
            </div>
            <Badge
                v-if="weather.mode_label"
                variant="outline"
            >
                {{ weather.mode_label }}
            </Badge>
        </CardHeader>

        <CardContent class="space-y-4">
            <p
                v-if="isUnavailable"
                class="text-sm text-muted-foreground"
            >
                {{ weather?.message ?? 'Weather is not available for this trip yet.' }}
            </p>

            <template v-else>
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
                    v-if="weather.mode === 'forecast' && weather.days?.length"
                    class="-mx-1 flex gap-2 overflow-x-auto pb-1"
                >
                    <div
                        v-for="day in weather.days"
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
