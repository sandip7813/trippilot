<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TripLocation } from '@/types/trip';

defineProps<{
    prefix: 'origin' | 'destination';
    label: string;
    location?: TripLocation | null;
    errors: Record<string, string>;
    required?: boolean;
    hint?: string;
}>();
</script>

<template>
    <div class="grid gap-3 rounded-lg border border-border/60 bg-muted/20 p-4">
        <div class="grid gap-2">
            <Label :for="`${prefix}-label`">
                {{ label }}
                <span v-if="required" class="text-destructive">*</span>
            </Label>
            <Input
                :id="`${prefix}-label`"
                :name="`${prefix}[label]`"
                :default-value="location?.label ?? ''"
                :required="required"
                placeholder="City, region, or country"
            />
            <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
            <InputError :message="errors[`${prefix}.label`] ?? errors[`${prefix}[label]`]" />
        </div>

        <details class="group">
            <summary
                class="cursor-pointer text-xs font-medium text-muted-foreground hover:text-foreground"
            >
                Optional coordinates (for maps & routing)
            </summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="`${prefix}-lat`" class="text-xs">Latitude</Label>
                    <Input
                        :id="`${prefix}-lat`"
                        :name="`${prefix}[lat]`"
                        type="number"
                        step="any"
                        min="-90"
                        max="90"
                        :default-value="location?.lat ?? ''"
                        placeholder="e.g. 19.0760"
                    />
                    <InputError :message="errors[`${prefix}.lat`]" />
                </div>
                <div class="grid gap-2">
                    <Label :for="`${prefix}-lng`" class="text-xs">Longitude</Label>
                    <Input
                        :id="`${prefix}-lng`"
                        :name="`${prefix}[lng]`"
                        type="number"
                        step="any"
                        min="-180"
                        max="180"
                        :default-value="location?.lng ?? ''"
                        placeholder="e.g. 72.8777"
                    />
                    <InputError :message="errors[`${prefix}.lng`]" />
                </div>
            </div>
        </details>
    </div>
</template>
