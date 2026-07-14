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
    RoadProfile,
    RoadTrip,
    RoadTripFormOptions,
} from '@/types/roadTrip';
import type { TripLocation, TripWaypoint } from '@/types/trip';

const props = defineProps<
    {
        trip?: RoadTrip;
        defaultOrigin?: TripLocation | null;
        errors: Record<string, string>;
    } & RoadTripFormOptions
>();

const profile = computed(
    (): RoadProfile =>
        props.trip?.road_profile ?? {
            vehicle_class: 'car',
            fuel_type: 'petrol',
            driving_pace: 'standard',
            food_preference: 'any',
            avoid_tolls: false,
            avoid_highways: false,
            ev_range_km: null,
            max_drive_hours_per_day: null,
        },
);

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
const selectedFuelType = ref(profile.value.fuel_type);
const selectedVehicleClass = ref(profile.value.vehicle_class);

const today = isoToday();

const isBicycleTrip = computed(() => selectedVehicleClass.value === 'bicycle');

const visibleFuelTypes = computed(() =>
    isBicycleTrip.value
        ? props.fuelTypes.filter((option) => option.value === 'none')
        : props.fuelTypes.filter((option) => option.value !== 'none'),
);

watch(selectedVehicleClass, (vehicleClass) => {
    if (vehicleClass === 'bicycle') {
        selectedFuelType.value = 'none';
    } else if (selectedFuelType.value === 'none') {
        selectedFuelType.value = 'petrol';
    }
});

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

const showEvRange = computed(
    () =>
        selectedFuelType.value === 'ev' || selectedFuelType.value === 'hybrid',
);

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
                placeholder="Mumbai to Pune weekend drive"
            />
            <InputError :message="errors.title" />
        </div>

        <LocationField
            v-model="originLocation"
            prefix="origin"
            label="Starting from"
            :errors="errors"
            required
            require-selection
            hint="Where your drive begins."
        />

        <div class="space-y-3">
            <label class="flex items-center gap-2 text-sm font-medium">
                <input
                    v-model="isMultiCity"
                    type="checkbox"
                    class="size-4 rounded border border-input"
                />
                Multi-stop route
            </label>

            <input type="hidden" name="route_mode" :value="routeMode" />

            <WaypointListEditor
                v-if="isMultiCity"
                v-model:waypoints="waypoints"
                v-model:returns-to-origin="returnsToOrigin"
                variant="road"
                :show-nights="false"
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
                hint="Your final stop on this road trip."
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

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="road_profile_vehicle_class">Vehicle</Label>
                <select
                    id="road_profile_vehicle_class"
                    name="road_profile[vehicle_class]"
                    v-model="selectedVehicleClass"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    required
                >
                    <option
                        v-for="option in vehicleClasses"
                        :key="option.value"
                        :value="option.value"
                        :selected="profile.vehicle_class === option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors['road_profile.vehicle_class']" />
            </div>

            <div class="grid gap-2">
                <Label for="road_profile_fuel_type">Fuel / power</Label>
                <select
                    id="road_profile_fuel_type"
                    name="road_profile[fuel_type]"
                    v-model="selectedFuelType"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    required
                >
                    <option
                        v-for="option in visibleFuelTypes"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <p v-if="isBicycleTrip" class="text-xs text-muted-foreground">
                    Fuel and EV layers are hidden for bicycle trips. Use food,
                    hotels, viewpoints, and bike shops along the route instead.
                </p>
                <InputError :message="errors['road_profile.fuel_type']" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="road_profile_driving_pace">Driving pace</Label>
                <select
                    id="road_profile_driving_pace"
                    name="road_profile[driving_pace]"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Standard (optional)</option>
                    <option
                        v-for="option in drivingPaces"
                        :key="option.value"
                        :value="option.value"
                        :selected="profile.driving_pace === option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors['road_profile.driving_pace']" />
            </div>

            <div class="grid gap-2">
                <Label for="road_profile_food_preference"
                    >Food preference</Label
                >
                <select
                    id="road_profile_food_preference"
                    name="road_profile[food_preference]"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Any (optional)</option>
                    <option
                        v-for="option in foodPreferences"
                        :key="option.value"
                        :value="option.value"
                        :selected="profile.food_preference === option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors['road_profile.food_preference']" />
            </div>
        </div>

        <div v-if="showEvRange" class="grid gap-2">
            <Label for="road_profile_ev_range_km">EV range (km)</Label>
            <Input
                id="road_profile_ev_range_km"
                name="road_profile[ev_range_km]"
                type="number"
                min="50"
                max="800"
                :default-value="profile.ev_range_km ?? ''"
                placeholder="350"
                class="max-w-xs"
            />
            <InputError :message="errors['road_profile.ev_range_km']" />
        </div>

        <div v-if="!isBicycleTrip" class="flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-sm">
                <input
                    type="hidden"
                    name="road_profile[avoid_tolls]"
                    value="0"
                />
                <input
                    type="checkbox"
                    name="road_profile[avoid_tolls]"
                    value="1"
                    :checked="Boolean(profile.avoid_tolls)"
                    class="size-4 rounded border-input"
                />
                Avoid tolls
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input
                    type="hidden"
                    name="road_profile[avoid_highways]"
                    value="0"
                />
                <input
                    type="checkbox"
                    name="road_profile[avoid_highways]"
                    value="1"
                    :checked="Boolean(profile.avoid_highways)"
                    class="size-4 rounded border-input"
                />
                Avoid highways
            </label>
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
                placeholder="15000"
                class="max-w-xs"
            />
            <InputError :message="errors.budget" />
        </div>

        <div class="grid gap-2">
            <Label for="notes">Notes</Label>
            <textarea
                id="notes"
                name="notes"
                v-model="notes"
                rows="4"
                class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                placeholder="Scenic detours, dietary needs, preferred break frequency..."
            />
            <InputError :message="errors.notes" />
        </div>
    </div>
</template>
