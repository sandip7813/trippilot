<script setup lang="ts">
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import LocationField from '@/components/LocationField.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Trip, TripLocation, TripOption } from '@/types/trip';

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

const originLocation = computed(
    (): TripLocation | null | undefined => props.trip?.origin ?? props.defaultOrigin,
);

const isRoadTrip = computed(() => selectedType.value === 'road');
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
            prefix="origin"
            label="Starting from"
            :location="originLocation"
            :errors="errors"
            :required="isRoadTrip"
            hint="Where your journey begins. Defaults from your profile home city when set."
        />

        <LocationField
            prefix="destination"
            label="Destination"
            :location="trip?.destination"
            :errors="errors"
            hint="Where you are headed — main place or final stop."
        />

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
                <Label for="start_date">Start date</Label>
                <Input
                    id="start_date"
                    name="start_date"
                    type="date"
                    :default-value="trip?.start_date ?? ''"
                />
                <InputError :message="errors.start_date" />
            </div>

            <div class="grid gap-2">
                <Label for="end_date">End date</Label>
                <Input
                    id="end_date"
                    name="end_date"
                    type="date"
                    :default-value="trip?.end_date ?? ''"
                />
                <InputError :message="errors.end_date" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="budget">Budget (optional)</Label>
            <Input
                id="budget"
                name="budget"
                type="number"
                min="0"
                step="0.01"
                :default-value="trip?.budget ?? ''"
                placeholder="2500"
                class="max-w-xs"
            />
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
                rows="4"
                class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                :default-value="trip?.notes ?? ''"
                placeholder="Must-see spots, dietary needs, pace preferences..."
            />
            <InputError :message="errors.notes" />
        </div>
    </div>
</template>
