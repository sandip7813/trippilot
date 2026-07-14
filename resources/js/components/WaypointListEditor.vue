<script setup lang="ts">
import { GripVertical, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import LocationField from '@/components/LocationField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TripLocation, TripWaypoint } from '@/types/trip';

const props = defineProps<{
    errors: Record<string, string>;
    variant?: 'vacation' | 'road';
    showNights?: boolean;
}>();

const variant = computed(() => props.variant ?? 'vacation');
const showNights = computed(() => props.showNights ?? variant.value === 'vacation');

const description = computed(() =>
    variant.value === 'road'
        ? 'Add each stop in driving order. Your route will pass through every city.'
        : 'Add each city in visit order. We will plan trains, weather, and itinerary by leg.',
);

const waypoints = defineModel<TripWaypoint[]>('waypoints', { required: true });
const returnsToOrigin = defineModel<boolean>('returnsToOrigin', { required: true });

const canRemove = computed(() => waypoints.value.length > 2);

function addWaypoint(): void {
    if (waypoints.value.length >= 8) {
        return;
    }

    waypoints.value = [
        ...waypoints.value,
        {
            sequence: waypoints.value.length + 1,
            location: { label: null, lat: null, lng: null },
            nights: null,
            notes: null,
        },
    ];
}

function removeWaypoint(index: number): void {
    if (!canRemove.value) {
        return;
    }

    waypoints.value = waypoints.value
        .filter((_, waypointIndex) => waypointIndex !== index)
        .map((waypoint, waypointIndex) => ({
            ...waypoint,
            sequence: waypointIndex + 1,
        }));
}

function moveWaypoint(index: number, direction: -1 | 1): void {
    const targetIndex = index + direction;

    if (targetIndex < 0 || targetIndex >= waypoints.value.length) {
        return;
    }

    const next = [...waypoints.value];
    const [item] = next.splice(index, 1);
    next.splice(targetIndex, 0, item);

    waypoints.value = next.map((waypoint, waypointIndex) => ({
        ...waypoint,
        sequence: waypointIndex + 1,
    }));
}

function locationPrefix(index: number): string {
    return `waypoints.${index}.location`;
}
</script>

<template>
    <div class="space-y-4 rounded-xl border border-border/60 bg-muted/20 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <Label>Cities on your route</Label>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="waypoints.length >= 8"
                @click="addWaypoint"
            >
                <Plus class="mr-1.5 size-4" />
                Add city
            </Button>
        </div>

        <InputError :message="errors.waypoints" />

        <div class="space-y-4">
            <div
                v-for="(waypoint, index) in waypoints"
                :key="waypoint.sequence"
                class="rounded-lg border border-border/60 bg-background/80 p-4"
            >
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <GripVertical class="size-4 text-muted-foreground" />
                        City {{ index + 1 }}
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :disabled="index === 0"
                            @click="moveWaypoint(index, -1)"
                        >
                            ↑
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :disabled="index === waypoints.length - 1"
                            @click="moveWaypoint(index, 1)"
                        >
                            ↓
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 text-destructive"
                            :disabled="!canRemove"
                            @click="removeWaypoint(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>

                <input
                    type="hidden"
                    :name="`waypoints[${index}][sequence]`"
                    :value="waypoint.sequence"
                />

                <LocationField
                    v-model="waypoint.location as TripLocation"
                    :prefix="locationPrefix(index)"
                    :label="`City ${index + 1}`"
                    :errors="errors"
                    required
                    require-selection
                />

                <div v-if="showNights" class="mt-4 grid gap-2 sm:max-w-xs">
                    <Label :for="`waypoint-nights-${index}`">Nights (optional)</Label>
                    <Input
                        :id="`waypoint-nights-${index}`"
                        :name="`waypoints[${index}][nights]`"
                        type="number"
                        min="0"
                        max="30"
                        :model-value="waypoint.nights ?? ''"
                        placeholder="Auto"
                        @update:model-value="
                            (value) =>
                                (waypoint.nights =
                                    value === '' || value == null
                                        ? null
                                        : Number(value))
                        "
                    />
                    <InputError :message="errors[`waypoints.${index}.nights`]" />
                </div>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="returns_to_origin" :value="returnsToOrigin ? '1' : '0'" />
            <input
                v-model="returnsToOrigin"
                type="checkbox"
                class="size-4 rounded border border-input"
            />
            Return to starting city at the end
        </label>
    </div>
</template>
