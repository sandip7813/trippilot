<script setup lang="ts">
import { ExternalLink, ListTree, TrainFront } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripHubTrainHaltsDialog from '@/components/trip-hub/TripHubTrainHaltsDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDisplayDate } from '@/lib/dates';
import { formatTrainLabel } from '@/lib/trains';
import { cn } from '@/lib/utils';
import type {
    TripTrainLeg,
    TripTrainOption,
    TripTrainTimings,
} from '@/types/train';

const props = defineProps<{
    tripId: string;
    trainTimings: TripTrainTimings | null;
}>();

type LegTab = string;

const isUnavailable = computed(
    () => !props.trainTimings || props.trainTimings.available === false,
);

const legOptions = computed((): TripTrainLeg[] => {
    if ((props.trainTimings?.legs?.length ?? 0) > 0) {
        return props.trainTimings?.legs ?? [];
    }

    return [props.trainTimings?.outbound, props.trainTimings?.return].filter(
        (leg): leg is TripTrainLeg => leg !== undefined && leg !== null,
    );
});

const hasLegData = computed(() => legOptions.value.length > 0);

const activeTab = ref<LegTab>('1');

const tabs = computed(() =>
    legOptions.value.map((leg, index) => ({
        id: String(leg.sequence ?? index + 1),
        label:
            leg.route_label ||
            (leg.direction === 'return'
                ? 'Return'
                : leg.direction === 'inter_city'
                  ? `Leg ${leg.sequence ?? index + 1}`
                  : index === 0
                    ? 'Outbound'
                    : `Leg ${leg.sequence ?? index + 1}`),
        leg,
        count: leg.count ?? 0,
    })),
);

const activeLeg = computed(() => {
    const match = tabs.value.find((tab) => tab.id === activeTab.value);

    return match?.leg ?? tabs.value[0]?.leg ?? null;
});

watch(
    () => props.trainTimings,
    () => {
        const legs = legOptions.value;
        const firstAvailable = legs.find((leg) => leg.available)?.sequence;

        activeTab.value = String(firstAvailable ?? legs[0]?.sequence ?? 1);
    },
    { immediate: true },
);

const selectedTrain = ref<TripTrainOption | null>(null);
const selectedLeg = ref<TripTrainLeg | null>(null);
const haltsOpen = ref(false);

function openHalts(leg: TripTrainLeg, train: TripTrainOption): void {
    selectedLeg.value = leg;
    selectedTrain.value = train;
    haltsOpen.value = true;
}

function legDateLabel(leg: TripTrainLeg | null): string | null {
    if (!leg?.date) {
        return null;
    }

    return formatDisplayDate(leg.date);
}
</script>

<template>
    <Card class="card-vibrant overflow-hidden">
        <div
            class="h-1.5 bg-gradient-to-r from-orange-400 via-amber-500 to-rose-500"
        />
        <CardHeader
            class="flex flex-row flex-wrap items-start justify-between gap-3 pb-2"
        >
            <div>
                <CardTitle class="flex items-center gap-2 text-lg font-bold">
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-orange-500/15 text-orange-600 dark:text-orange-400"
                    >
                        <TrainFront class="size-4" />
                    </span>
                    Train timings
                </CardTitle>
                <p
                    v-if="
                        trainTimings?.route_label ||
                        (trainTimings?.origin_label &&
                            trainTimings?.destination_label)
                    "
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{
                        trainTimings?.route_label ??
                        `${trainTimings?.origin_label} → ${trainTimings?.destination_label}`
                    }}
                </p>
            </div>
            <div
                v-if="trainTimings?.from_station && trainTimings?.to_station"
                class="flex flex-wrap gap-2"
            >
                <Badge variant="outline">
                    {{ trainTimings.from_station.code }} →
                    {{ trainTimings.to_station.code }}
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <p v-if="!hasLegData" class="text-sm text-muted-foreground">
                {{
                    trainTimings?.message ??
                    'Train timings are not available for this trip yet.'
                }}
            </p>

            <template v-else>
                <div
                    v-if="trainTimings?.direct_only_notice"
                    class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-950 dark:text-amber-100"
                >
                    {{ trainTimings.direct_only_notice }}
                </div>

                <p
                    v-if="isUnavailable && trainTimings?.message"
                    class="text-sm text-muted-foreground"
                >
                    {{ trainTimings.message }}
                </p>

                <div
                    v-if="trainTimings?.destination_railhead"
                    class="rounded-lg border border-border/60 bg-muted/30 px-4 py-3 text-sm"
                >
                    <p class="font-medium">
                        Nearest mainline station for
                        {{ trainTimings.destination_railhead.place_label }}
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        {{ trainTimings.destination_railhead.station.name }}
                        ({{ trainTimings.destination_railhead.station.code }})
                    </p>
                    <p class="mt-2 text-muted-foreground">
                        {{ trainTimings.destination_railhead.last_mile }}
                    </p>
                </div>
                <div
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
                        <span class="max-w-[12rem] truncate">{{
                            tab.label
                        }}</span>
                        <Badge
                            v-if="tab.count > 0"
                            variant="secondary"
                            class="h-5 min-w-5 justify-center px-1.5 text-[10px]"
                        >
                            {{ tab.count }}
                        </Badge>
                    </button>
                </div>

                <div v-if="activeLeg" class="space-y-3">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <p class="text-sm font-semibold">
                                {{ activeLeg.route_label }}
                            </p>
                            <p
                                v-if="
                                    activeLeg.from_label && activeLeg.to_label
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{ activeLeg.from_label }} →
                                {{ activeLeg.to_label }}
                            </p>
                        </div>
                        <Badge v-if="legDateLabel(activeLeg)" variant="outline">
                            {{ legDateLabel(activeLeg) }}
                        </Badge>
                        <Badge
                            v-if="activeLeg.search_mode === 'railhead'"
                            variant="secondary"
                        >
                            Via railhead
                        </Badge>
                    </div>

                    <div
                        v-if="
                            activeLeg.message &&
                            (activeLeg.trains?.length ?? 0) > 0
                        "
                        class="rounded-lg border border-amber-500/25 bg-amber-500/5 px-4 py-3 text-sm text-muted-foreground"
                    >
                        {{ activeLeg.message }}
                    </div>

                    <p
                        v-if="
                            activeLeg.railhead?.last_mile &&
                            activeLeg.search_mode === 'railhead'
                        "
                        class="text-xs text-muted-foreground"
                    >
                        {{ activeLeg.railhead.last_mile }}
                    </p>

                    <p
                        v-if="
                            !activeLeg.available ||
                            (activeLeg.trains?.length ?? 0) === 0
                        "
                        class="rounded-lg border border-dashed border-border/70 px-4 py-3 text-sm text-muted-foreground"
                    >
                        {{
                            activeLeg.message ??
                            'No trains found for this direction on the selected date.'
                        }}
                    </p>

                    <div
                        v-else
                        class="overflow-x-auto rounded-lg border border-border/60"
                    >
                        <table class="w-full min-w-[48rem] text-sm">
                            <thead>
                                <tr
                                    class="border-b border-border/60 bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <th class="px-3 py-2 font-medium">Train</th>
                                    <th class="px-3 py-2 font-medium">
                                        Departs
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Arrives
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Duration
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Distance
                                    </th>
                                    <th class="px-3 py-2 font-medium">Halts</th>
                                    <th class="px-3 py-2 font-medium">Runs</th>
                                    <th class="px-3 py-2 font-medium" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="train in activeLeg.trains ?? []"
                                    :key="`${activeLeg.direction}-${train.number}-${train.departure}`"
                                    class="border-b border-border/40 last:border-b-0"
                                >
                                    <td class="px-3 py-2.5 align-top">
                                        <p
                                            class="max-w-[14rem] leading-snug font-medium"
                                        >
                                            {{ formatTrainLabel(train) }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <Badge
                                                v-if="train.type"
                                                variant="outline"
                                                class="text-[10px]"
                                            >
                                                {{ train.type }}
                                            </Badge>
                                            <Badge
                                                v-if="train.category"
                                                variant="secondary"
                                                class="text-[10px]"
                                            >
                                                {{ train.category }}
                                            </Badge>
                                        </div>
                                        <p
                                            v-if="train.live?.platform"
                                            class="mt-1 text-[11px] text-muted-foreground"
                                        >
                                            Platform {{ train.live.platform }}
                                            <span
                                                v-if="
                                                    train.live.delay_minutes &&
                                                    train.live.delay_minutes > 0
                                                "
                                            >
                                                ·
                                                {{ train.live.delay_minutes }}m
                                                delay
                                            </span>
                                        </p>
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <p class="font-medium">
                                            {{ train.departure ?? '—' }}
                                        </p>
                                        <p
                                            v-if="
                                                train.departure_day &&
                                                train.departure_day > 1
                                            "
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Day {{ train.departure_day }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <p class="font-medium">
                                            {{ train.arrival ?? '—' }}
                                        </p>
                                        <p
                                            v-if="
                                                train.day_offset &&
                                                train.day_offset > 0
                                            "
                                            class="text-[11px] text-amber-700 dark:text-amber-300"
                                        >
                                            +{{ train.day_offset }} day{{
                                                train.day_offset === 1
                                                    ? ''
                                                    : 's'
                                            }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-3 py-2.5 align-top text-muted-foreground"
                                    >
                                        {{ train.duration_label ?? '—' }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 align-top text-muted-foreground"
                                    >
                                        {{
                                            train.distance_km != null
                                                ? `${train.distance_km.toFixed(1)} km`
                                                : '—'
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 align-top text-muted-foreground"
                                    >
                                        {{
                                            train.total_halts_between != null
                                                ? train.total_halts_between
                                                : '—'
                                        }}
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <span
                                            v-if="train.runs_daily"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Daily
                                        </span>
                                        <div
                                            v-else
                                            class="flex flex-wrap gap-1"
                                        >
                                            <Badge
                                                v-for="day in train.run_days"
                                                :key="day"
                                                variant="secondary"
                                                class="px-1.5 py-0 text-[10px]"
                                            >
                                                {{ day }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2.5 text-xs whitespace-nowrap"
                                            @click="openHalts(activeLeg, train)"
                                        >
                                            <ListTree class="mr-1.5 size-3.5" />
                                            Halts
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="!isUnavailable"
                    class="flex flex-col gap-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                >
                    <p v-if="trainTimings?.disclaimer">
                        {{ trainTimings.disclaimer }}
                    </p>
                    <a
                        href="https://www.irctc.co.in/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex shrink-0 items-center gap-1.5 text-primary hover:underline"
                    >
                        Book on IRCTC
                        <ExternalLink class="size-3.5" />
                    </a>
                </div>
            </template>
        </CardContent>
    </Card>

    <TripHubTrainHaltsDialog
        v-if="selectedTrain && selectedLeg"
        :key="`${selectedLeg.direction}-${selectedTrain.number}`"
        v-model:open="haltsOpen"
        :trip-id="tripId"
        :leg="selectedLeg"
        :train="selectedTrain"
    />
</template>
