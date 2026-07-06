<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Trip, TripOption } from '@/types/trip';

defineProps<{
    trip?: Trip;
    tripTypes: TripOption[];
    tripStatuses?: TripOption[];
    errors: Record<string, string>;
    showStatus?: boolean;
}>();
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

        <div class="grid gap-2">
            <Label for="destination">Destination</Label>
            <Input
                id="destination"
                name="destination"
                :default-value="trip?.destination ?? ''"
                placeholder="Rome, Italy"
            />
            <InputError :message="errors.destination" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="type">Trip type</Label>
                <select
                    id="type"
                    name="type"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    :default-value="trip?.type ?? 'vacation'"
                >
                    <option
                        v-for="option in tripTypes"
                        :key="option.value"
                        :value="option.value"
                        :selected="(trip?.type ?? 'vacation') === option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors.type" />
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
                />
                <InputError :message="errors.travelers" />
            </div>
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
                placeholder="Travel style, must-see spots, dietary needs..."
            />
            <InputError :message="errors.notes" />
        </div>
    </div>
</template>
