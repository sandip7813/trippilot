<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { formatTrainLabel } from '@/lib/trains';
import type {
    TripTrainHaltsResponse,
    TripTrainLeg,
    TripTrainOption,
} from '@/types/train';

const props = defineProps<{
    tripId: string;
    leg: TripTrainLeg;
    train: TripTrainOption;
}>();

const open = defineModel<boolean>('open', { default: false });

const loading = ref(false);
const error = ref<string | null>(null);
const halts = ref<TripTrainHaltsResponse | null>(null);

const title = computed(() => formatTrainLabel(props.train));

const subtitle = computed(
    () =>
        `${props.leg.route_label}${props.leg.date ? ` · ${props.leg.date}` : ''}`,
);

async function loadHalts(): Promise<void> {
    if (!open.value) {
        return;
    }

    loading.value = true;
    error.value = null;
    halts.value = null;

    try {
        const url = TripController.trainHalts.url(
            { trip: props.tripId, trainNumber: props.train.number },
            {
                query: {
                    from: props.train.from_station.code,
                    to: props.train.to_station.code,
                    date: props.leg.date ?? undefined,
                    from_sequence: props.train.from_sequence ?? undefined,
                    to_sequence: props.train.to_sequence ?? undefined,
                },
            },
        );

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Could not load halt details.');
        }

        halts.value = (await response.json()) as TripTrainHaltsResponse;

        if (!halts.value.available) {
            error.value =
                halts.value.message ??
                'Halt details are not available for this train.';
        }
    } catch {
        error.value = 'Could not load halt details. Please try again.';
    } finally {
        loading.value = false;
    }
}

watch(
    () => [open.value, props.train.number, props.leg.direction] as const,
    () => {
        void loadHalts();
    },
    { immediate: true },
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] gap-0 overflow-hidden p-0 sm:max-w-3xl">
            <DialogHeader class="space-y-1 border-b border-border/60 px-6 py-4">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ subtitle }}</DialogDescription>
            </DialogHeader>

            <div class="max-h-[calc(85vh-5.5rem)] overflow-y-auto px-6 py-4">
                <div
                    v-if="loading"
                    class="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"
                >
                    <Spinner class="size-4" />
                    Loading halt details...
                </div>

                <p v-else-if="error" class="py-6 text-sm text-muted-foreground">
                    {{ error }}
                </p>

                <div v-else-if="halts?.halts?.length" class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="outline">
                            {{ halts.halt_count ?? 0 }} intermediate halt{{
                                (halts.halt_count ?? 0) === 1 ? '' : 's'
                            }}
                        </Badge>
                        <Badge v-if="train.distance_km != null" variant="secondary">
                            {{ train.distance_km.toFixed(1) }} km
                        </Badge>
                        <Badge v-if="train.duration_label" variant="secondary">
                            {{ train.duration_label }}
                        </Badge>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-border/60">
                        <table class="w-full min-w-[32rem] text-sm">
                            <thead>
                                <tr
                                    class="border-b border-border/60 bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    <th class="px-3 py-2 font-medium">#</th>
                                    <th class="px-3 py-2 font-medium">Station</th>
                                    <th class="px-3 py-2 font-medium">Arrival</th>
                                    <th class="px-3 py-2 font-medium">Departure</th>
                                    <th class="px-3 py-2 font-medium">Halt</th>
                                    <th class="px-3 py-2 font-medium">Platform</th>
                                    <th class="px-3 py-2 font-medium">Distance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="halt in halts.halts"
                                    :key="`${halt.code}-${halt.sequence}`"
                                    class="border-b border-border/40 last:border-b-0"
                                    :class="{
                                        'bg-orange-500/5': halt.is_boarding || halt.is_alighting,
                                    }"
                                >
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        {{ halt.sequence ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <p class="font-medium">
                                            {{ halt.code }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ halt.name }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <Badge
                                                v-if="halt.is_boarding"
                                                variant="outline"
                                                class="text-[10px]"
                                            >
                                                Board
                                            </Badge>
                                            <Badge
                                                v-if="halt.is_alighting"
                                                variant="outline"
                                                class="text-[10px]"
                                            >
                                                Alight
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 font-medium">
                                        {{ halt.arrival ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 font-medium">
                                        {{ halt.departure ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        {{
                                            halt.halt_minutes != null
                                                ? `${halt.halt_minutes}m`
                                                : halt.is_halt
                                                  ? '—'
                                                  : 'Pass'
                                        }}
                                    </td>
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        {{ halt.platform ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        {{
                                            halt.distance_km != null
                                                ? `${halt.distance_km.toFixed(1)} km`
                                                : '—'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p
                    v-else
                    class="py-6 text-sm text-muted-foreground"
                >
                    No halt details are available for this train segment.
                </p>
            </div>

            <div class="border-t border-border/60 px-6 py-4">
                <Button type="button" variant="outline" @click="open = false">
                    Close
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
