<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Loader2, MapPin } from '@lucide/vue';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { search as locationSearch } from '@/routes/locations';
import type { TripLocation } from '@/types/trip';

const props = defineProps<{
    prefix: 'origin' | 'destination' | 'home_city';
    label: string;
    errors: Record<string, string>;
    required?: boolean;
    requireSelection?: boolean;
    hint?: string;
    variant?: 'default' | 'compact';
}>();

const model = defineModel<TripLocation | null>({ default: null });

const variant = computed(() => props.variant ?? 'default');

const page = usePage();

const locationSearchEnabled = computed(
    () => page.props.integrations?.locationSearchEnabled ?? false,
);

const query = ref(model.value?.label ?? '');
const open = ref(false);
const loading = ref(false);
const suggestions = ref<TripLocation[]>([]);
const rootRef = ref<HTMLElement | null>(null);
const selectionError = ref<string | null>(null);

const mustPickFromSearch = computed(
    () => (props.requireSelection ?? false) && locationSearchEnabled.value,
);

function hasValidSelection(location: TripLocation | null | undefined): boolean {
    return location?.lat != null && location?.lng != null && Boolean(location.label?.trim());
}

onClickOutside(rootRef, () => {
    open.value = false;
});

watch(
    model,
    (value) => {
        if ((value?.label ?? '') !== query.value) {
            query.value = value?.label ?? '';
        }
    },
    { deep: true },
);

const debouncedSearch = useDebounceFn(async (value: string) => {
    const trimmed = value.trim();

    if (! locationSearchEnabled.value || trimmed.length < 2) {
        suggestions.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(locationSearch.url({ query: { q: trimmed } }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            suggestions.value = [];

            return;
        }

        const data = (await response.json()) as { results: TripLocation[] };

        suggestions.value = data.results ?? [];
        open.value = suggestions.value.length > 0;
    } finally {
        loading.value = false;
    }
}, 300);

function syncFreeText(value: string): void {
    if (mustPickFromSearch.value) {
        return;
    }

    const trimmed = value.trim();

    model.value = trimmed
        ? {
            label: trimmed,
            lat: null,
            lng: null,
            place_id: null,
            country_code: null,
        }
        : null;
}

function onInput(event: Event): void {
    const value = (event.target as HTMLInputElement).value;

    query.value = value;
    selectionError.value = null;

    if (locationSearchEnabled.value) {
        if (mustPickFromSearch.value) {
            if (! hasValidSelection(model.value) || value !== model.value?.label) {
                model.value = null;
            }

            open.value = true;
            void debouncedSearch(value);

            return;
        }

        if (model.value?.label && value !== model.value.label) {
            syncFreeText(value);
        }

        open.value = true;
        void debouncedSearch(value);

        return;
    }

    syncFreeText(value);
}

function selectSuggestion(suggestion: TripLocation): void {
    model.value = {
        label: suggestion.label,
        lat: suggestion.lat,
        lng: suggestion.lng,
        place_id: suggestion.place_id ?? null,
        country_code: suggestion.country_code ?? null,
    };
    query.value = suggestion.label ?? '';
    open.value = false;
    suggestions.value = [];
    selectionError.value = null;
}

function onFocus(): void {
    if (locationSearchEnabled.value && query.value.trim().length >= 2) {
        void debouncedSearch(query.value);
    }
}

function onBlur(): void {
    window.setTimeout(() => {
        if (open.value) {
            return;
        }

        if (mustPickFromSearch.value) {
            if (! hasValidSelection(model.value)) {
                query.value = '';
                model.value = null;
                selectionError.value = 'Pick a place from the search suggestions.';
            }

            return;
        }

        if (! open.value && query.value.trim() !== (model.value?.label ?? '')) {
            syncFreeText(query.value);
        }
    }, 150);
}
</script>

<template>
    <div
        :class="variant === 'compact'
            ? 'grid gap-2'
            : 'grid gap-3 rounded-lg border border-border/60 bg-muted/20 p-4'"
    >
        <div ref="rootRef" class="relative grid gap-2">
            <Label :for="`${prefix}-label`">
                {{ label }}
                <span v-if="required" class="text-destructive">*</span>
            </Label>

            <div class="relative">
                <Input
                    :id="`${prefix}-label`"
                    :model-value="query"
                    autocomplete="off"
                    :required="required"
                    :placeholder="locationSearchEnabled ? 'Search city or region...' : 'City, region, or country'"
                    class="pr-9"
                    @input="onInput"
                    @focus="onFocus"
                    @blur="onBlur"
                />
                <Loader2
                    v-if="loading"
                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                />
                <MapPin
                    v-else
                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>

            <div
                v-if="open && suggestions.length > 0"
                class="absolute top-full right-0 left-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md border border-border bg-popover py-1 text-popover-foreground shadow-lg"
            >
                <button
                    v-for="(suggestion, index) in suggestions"
                    :key="`${suggestion.place_id ?? suggestion.label}-${index}`"
                    type="button"
                    class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                    @mousedown.prevent="selectSuggestion(suggestion)"
                >
                    <MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    <span>{{ suggestion.label }}</span>
                </button>
            </div>

            <input
                type="hidden"
                :name="`${prefix}[label]`"
                :value="model?.label ?? ''"
            />
            <input
                type="hidden"
                :name="`${prefix}[lat]`"
                :value="model?.lat ?? ''"
            />
            <input
                type="hidden"
                :name="`${prefix}[lng]`"
                :value="model?.lng ?? ''"
            />
            <input
                type="hidden"
                :name="`${prefix}[place_id]`"
                :value="model?.place_id ?? ''"
            />
            <input
                type="hidden"
                :name="`${prefix}[country_code]`"
                :value="model?.country_code ?? ''"
            />

            <p
                v-if="hint"
                class="text-xs text-muted-foreground"
            >
                {{ hint }}
            </p>
            <p
                v-if="locationSearchEnabled && variant !== 'compact'"
                class="text-xs text-muted-foreground"
            >
                <template v-if="mustPickFromSearch">
                    Start typing, then pick a suggestion — manual text is not accepted.
                </template>
                <template v-else>
                    Start typing to search places. Pick a suggestion for accurate maps and trip scope.
                </template>
            </p>
            <p
                v-else-if="hasValidSelection(model)"
                class="text-xs text-muted-foreground"
            >
                Coordinates saved from your selection.
            </p>

            <InputError :message="selectionError ?? errors[`${prefix}.label`] ?? errors[`${prefix}[label]`] ?? errors[`${prefix}.lat`] ?? errors[`${prefix}[lat]`]" />
        </div>
    </div>
</template>
