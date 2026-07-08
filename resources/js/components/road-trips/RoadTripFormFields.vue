<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputError from '@/components/InputError.vue';
import LocationField from '@/components/LocationField.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { isoToday } from '@/lib/dates';
import type {
    RoadProfile,
    RoadTrip,
    RoadTripFormOptions,
} from '@/types/roadTrip';
import type { TripLocation } from '@/types/trip';

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
const selectedFuelType = ref(profile.value.fuel_type);

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

const showEvRange = computed(
    () =>
        selectedFuelType.value === 'ev' || selectedFuelType.value === 'hybrid',
);

watch(startDateIso, (nextStart) => {
    if (nextStart && endDateIso.value && endDateIso.value < nextStart) {
        endDateIso.value = nextStart;
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

        <LocationField
            v-model="destinationLocation"
            prefix="destination"
            label="Destination"
            :errors="errors"
            required
            require-selection
            hint="Your final stop on this road trip."
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="road_profile_vehicle_class">Vehicle</Label>
                <select
                    id="road_profile_vehicle_class"
                    name="road_profile[vehicle_class]"
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
                        v-for="option in fuelTypes"
                        :key="option.value"
                        :value="option.value"
                        :selected="profile.fuel_type === option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
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

        <div class="flex flex-wrap gap-6">
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
