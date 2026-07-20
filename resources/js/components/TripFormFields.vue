<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputError from '@/components/InputError.vue';
import LocationField from '@/components/LocationField.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WaypointListEditor from '@/components/WaypointListEditor.vue';
import { isoToday } from '@/lib/dates';
import type {
    Trip,
    TripLocation,
    TripOption,
    TripWaypoint,
} from '@/types/trip';

const props = defineProps<{
    trip?: Trip;
    tripTypes: TripOption[];
    tripStatuses?: TripOption[];
    travelStyles?: TripOption[];
    defaultOrigin?: TripLocation | null;
    errors: Record<string, string>;
    showStatus?: boolean;
}>();

const selectedType = ref(props.trip?.type ?? 'vacation');
const startDateIso = ref(props.trip?.start_date ?? '');
const endDateIso = ref(props.trip?.end_date ?? '');
const originLocation = ref<TripLocation | null>(
    props.trip?.origin ?? props.defaultOrigin ?? null,
);
const destinationLocation = ref<TripLocation | null>(
    props.trip?.destination ?? null,
);
const notes = ref(props.trip?.notes ?? '');
const isMultiCity = ref(
    props.trip?.route_mode === 'multi_city' ||
        (props.trip?.waypoints?.length ?? 0) >= 2,
);
const returnsToOrigin = ref(props.trip?.returns_to_origin ?? true);
const waypoints = ref<TripWaypoint[]>(
    props.trip?.waypoints?.length
        ? props.trip.waypoints.map((waypoint, index) => ({
              ...waypoint,
              sequence: waypoint.sequence ?? index + 1,
          }))
        : [
              {
                  sequence: 1,
                  location: { label: null, lat: null, lng: null },
                  nights: null,
              },
              {
                  sequence: 2,
                  location: { label: null, lat: null, lng: null },
                  nights: null,
              },
          ],
);

const today = isoToday();

const minStartDate = computed((): string => {
    if (props.trip?.start_date && props.trip.start_date < today) {
        return props.trip.start_date;
    }

    return today;
});

const minEndDate = computed(
    (): string => startDateIso.value || minStartDate.value,
);

const routeMode = computed(() => (isMultiCity.value ? 'multi_city' : 'simple'));

const syncedDestination = computed(() => {
    if (!isMultiCity.value) {
        return destinationLocation.value;
    }

    const lastWaypoint = waypoints.value[waypoints.value.length - 1];

    return lastWaypoint?.location ?? null;
});

watch(startDateIso, (nextStart) => {
    if (nextStart && endDateIso.value && endDateIso.value < nextStart) {
        endDateIso.value = nextStart;
    }
});

watch(isMultiCity, (enabled) => {
    if (enabled && waypoints.value.length < 2) {
        waypoints.value = [
            {
                sequence: 1,
                location: destinationLocation.value ?? {
                    label: null,
                    lat: null,
                    lng: null,
                },
                nights: null,
            },
            {
                sequence: 2,
                location: { label: null, lat: null, lng: null },
                nights: null,
            },
        ];
    }
});
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="title">Trip title</Label>
            <Input
                id="title"
                name="title"
                :default-value="trip?.title"
                required
                placeholder="Summer in Italy"
            />
            <InputError :message="errors.title" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="type">Planning mode</Label>
                <select
                    id="type"
                    name="type"
                    v-model="selectedType"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option
                        v-for="option in tripTypes"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors.type" />
            </div>

            <div v-if="travelStyles" class="grid gap-2">
                <Label for="travel_style">Travel style</Label>
                <select
                    id="travel_style"
                    name="travel_style"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Select style (optional)</option>
                    <option
                        v-for="option in travelStyles"
                        :key="option.value"
                        :value="option.value"
                        :selected="trip?.travel_style === option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors.travel_style" />
            </div>
        </div>

        <LocationField
            v-model="originLocation"
            prefix="origin"
            label="Starting from"
            :errors="errors"
            required
            require-selection
            hint="Where your journey begins. Defaults from your profile home city when set."
        />

        <div class="space-y-3">
            <label class="flex items-center gap-2 text-sm font-medium">
                <input
                    v-model="isMultiCity"
                    type="checkbox"
                    class="size-4 rounded border border-input"
                />
                Multi-city trip
            </label>

            <input type="hidden" name="route_mode" :value="routeMode" />

            <WaypointListEditor
                v-if="isMultiCity"
                v-model:waypoints="waypoints"
                v-model:returns-to-origin="returnsToOrigin"
                :errors="errors"
            />

            <LocationField
                v-else
                v-model="destinationLocation"
                prefix="destination"
                label="Destination"
                :errors="errors"
                required
                require-selection
                hint="Where you are headed — main place or final stop."
            />

            <template v-if="isMultiCity && syncedDestination">
                <input
                    type="hidden"
                    name="destination[label]"
                    :value="syncedDestination.label ?? ''"
                />
                <input
                    type="hidden"
                    name="destination[lat]"
                    :value="syncedDestination.lat ?? ''"
                />
                <input
                    type="hidden"
                    name="destination[lng]"
                    :value="syncedDestination.lng ?? ''"
                />
                <input
                    type="hidden"
                    name="destination[place_id]"
                    :value="syncedDestination.place_id ?? ''"
                />
                <input
                    type="hidden"
                    name="destination[country_code]"
                    :value="syncedDestination.country_code ?? ''"
                />
            </template>
        </div>

        <div class="grid gap-2">
            <Label for="travelers">Travelers</Label>
            <Input
                id="travelers"
                name="travelers"
                type="number"
                min="1"
                max="50"
                :default-value="trip?.travelers ?? 1"
                required
                class="max-w-xs"
            />
            <InputError :message="errors.travelers" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label id="start_date-label" for="start_date">Start date</Label>
                <input type="hidden" name="start_date" :value="startDateIso" />
                <DatePickerField
                    id="start_date"
                    v-model="startDateIso"
                    :min="minStartDate"
                />
                <p class="text-xs text-muted-foreground">
                    Click to pick a date. Today or later{{
                        trip ? ', unless keeping an existing date' : ''
                    }}.
                </p>
                <InputError :message="errors.start_date" />
            </div>

            <div class="grid gap-2">
                <Label id="end_date-label" for="end_date">End date</Label>
                <input type="hidden" name="end_date" :value="endDateIso" />
                <DatePickerField
                    id="end_date"
                    v-model="endDateIso"
                    :min="minEndDate"
                />
                <p class="text-xs text-muted-foreground">
                    Click to pick a date on or after the start date.
                </p>
                <InputError :message="errors.end_date" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="budget">Budget in INR (optional)</Label>
            <Input
                id="budget"
                name="budget"
                type="number"
                min="0"
                step="0.01"
                :default-value="trip?.budget ?? ''"
                placeholder="25000"
                class="max-w-xs"
            />
            <p class="text-xs text-muted-foreground">
                Enter your total trip budget in rupees (₹).
            </p>
            <InputError :message="errors.budget" />
        </div>

        <div v-if="showStatus && tripStatuses" class="grid gap-2">
            <Label for="status">Status</Label>
            <select
                id="status"
                name="status"
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option
                    v-for="option in tripStatuses"
                    :key="option.value"
                    :value="option.value"
                    :selected="trip?.status === option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <InputError :message="errors.status" />
        </div>

        <div class="grid gap-2">
            <Label for="notes">Notes</Label>
            <textarea
                id="notes"
                name="notes"
                v-model="notes"
                rows="4"
                class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                placeholder="Must-see spots, dietary needs, pace preferences..."
            />
            <InputError :message="errors.notes" />
        </div>
    </div>
</template>
