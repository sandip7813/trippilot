<script setup lang="ts">
import { CloudSun } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripWeatherSegmentPanel from '@/components/TripWeatherSegmentPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { TripWeather, TripWeatherSegment } from '@/types/weather';

const props = withDefaults(
    defineProps<{
        weather: TripWeather | null;
        class?: string;
    }>(),
    {
        class: undefined,
    },
);

const isUnavailable = computed(
    () => props.weather == null || props.weather.available === false,
);

const weatherSegments = computed(() => props.weather?.segments ?? []);

const isMultiCityWeather = computed(() => weatherSegments.value.length > 1);

const singleCitySegment = computed((): TripWeatherSegment | null => {
    if (props.weather == null || isMultiCityWeather.value) {
        return null;
    }

    return props.weather as TripWeatherSegment;
});

const activeTab = ref('1');

const tabs = computed(() =>
    weatherSegments.value.map((segment, index) => ({
        id: String(segment.sequence ?? index + 1),
        label: shortCityLabel(segment.segment_label ?? segment.location_label),
        segment,
    })),
);

const activeSegment = computed(() => {
    const match = tabs.value.find((tab) => tab.id === activeTab.value);

    return match?.segment ?? tabs.value[0]?.segment ?? null;
});

watch(
    () => props.weather,
    () => {
        const firstAvailable = weatherSegments.value.find(
            (segment) => segment.available !== false,
        );

        activeTab.value = String(
            firstAvailable?.sequence ?? weatherSegments.value[0]?.sequence ?? 1,
        );
    },
    { immediate: true },
);

function shortCityLabel(label: string | null | undefined): string {
    if (!label) {
        return 'Stop';
    }

    const primary = label.split(',')[0]?.trim();

    return primary || label;
}
</script>

<template>
    <Card
        :class="
            cn('card-vibrant overflow-hidden', props.class ?? 'h-full')
        "
    >
        <div
            class="h-1.5 bg-gradient-to-r from-sky-400 via-cyan-500 to-indigo-500"
        />
        <CardHeader
            class="flex flex-row flex-wrap items-start justify-between gap-3 pb-2"
        >
            <div>
                <CardTitle class="flex items-center gap-2 text-lg font-bold">
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400"
                    >
                        <CloudSun class="size-4" />
                    </span>
                    Weather
                </CardTitle>
                <p
                    v-if="weather?.location_label && !isMultiCityWeather"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ weather.location_label }}
                </p>
                <p
                    v-else-if="isMultiCityWeather"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Select a stop to see its forecast
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Badge v-if="weather?.mode_label" variant="outline">
                    {{ weather.mode_label }}
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <p v-if="isUnavailable" class="text-sm text-muted-foreground">
                {{
                    weather?.message ??
                    'Weather is not available for this trip yet.'
                }}
            </p>

            <template v-else-if="weather">
                <div
                    v-if="isMultiCityWeather"
                    class="flex gap-1 overflow-x-auto rounded-xl border border-border/60 bg-muted/20 p-1.5"
                    role="tablist"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.id"
                        :class="
                            cn(
                                'inline-flex shrink-0 items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors',
                                activeTab === tab.id
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                            )
                        "
                        @click="activeTab = tab.id"
                    >
                        <span class="max-w-[10rem] truncate">{{
                            tab.label
                        }}</span>
                    </button>
                </div>

                <TripWeatherSegmentPanel
                    v-if="isMultiCityWeather && activeSegment"
                    :key="`${activeSegment.segment_label}-${activeSegment.date_from}`"
                    :segment="activeSegment"
                    compact
                />

                <TripWeatherSegmentPanel
                    v-else-if="singleCitySegment"
                    :segment="singleCitySegment"
                    compact
                />

                <p
                    v-if="isMultiCityWeather && weather.disclaimer"
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    {{ weather.disclaimer }}
                </p>
            </template>
        </CardContent>
    </Card>
</template>
