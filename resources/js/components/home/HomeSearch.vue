<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, Compass, MapPin, Search } from '@lucide/vue';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatShortDate } from '@/lib/dates';
import {
    index as openTripsIndex,
    show as openTripShow,
    suggestions as openTripSuggestions,
} from '@/routes/open-trips';

type DestinationSuggestion = {
    label: string;
    name: string;
    trip_count: number;
};

type TripSuggestion = {
    id: string;
    title: string;
    destination: string | null;
    start_date: string | null;
};

type Option =
    | { kind: 'destination'; key: string; item: DestinationSuggestion }
    | { kind: 'trip'; key: string; item: TripSuggestion };

const MIN_QUERY_LENGTH = 2;

const query = ref('');
const destinations = ref<DestinationSuggestion[]>([]);
const trips = ref<TripSuggestion[]>([]);
const isFetching = ref(false);
const isNavigating = ref(false);
const isOpen = ref(false);
const activeIndex = ref(-1);
const hasSearched = ref(false);

const container = useTemplateRef<HTMLElement>('container');
let pendingRequest: AbortController | null = null;

onClickOutside(container, () => {
    isOpen.value = false;
});

onBeforeUnmount(() => pendingRequest?.abort());

const options = computed<Option[]>(() => [
    ...destinations.value.map((item) => ({
        kind: 'destination' as const,
        key: `destination:${item.label}`,
        item,
    })),
    ...trips.value.map((item) => ({
        kind: 'trip' as const,
        key: `trip:${item.id}`,
        item,
    })),
]);

const trimmedQuery = computed(() => query.value.trim());

const showDropdown = computed(
    () =>
        isOpen.value &&
        trimmedQuery.value.length >= MIN_QUERY_LENGTH &&
        hasSearched.value,
);

/** Whether the suggestion list is showing, so the page can lift the hero. */
const dropdownVisible = defineModel<boolean>('open', { default: false });

watch(showDropdown, (visible) => {
    dropdownVisible.value = visible;
});

const fetchSuggestions = useDebounceFn(async (term: string) => {
    pendingRequest?.abort();

    if (term.length < MIN_QUERY_LENGTH) {
        isFetching.value = false;

        return;
    }

    const controller = new AbortController();
    pendingRequest = controller;

    try {
        const response = await fetch(
            openTripSuggestions.url({ query: { q: term } }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            },
        );

        if (!response.ok) {
            destinations.value = [];
            trips.value = [];

            return;
        }

        const data = (await response.json()) as {
            destinations: DestinationSuggestion[];
            trips: TripSuggestion[];
        };

        destinations.value = data.destinations ?? [];
        trips.value = data.trips ?? [];
        activeIndex.value = -1;
        hasSearched.value = true;
        isOpen.value = true;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            destinations.value = [];
            trips.value = [];
        }
    } finally {
        if (pendingRequest === controller) {
            isFetching.value = false;
            pendingRequest = null;
        }
    }
}, 250);

function onInput(): void {
    if (trimmedQuery.value.length < MIN_QUERY_LENGTH) {
        pendingRequest?.abort();
        isFetching.value = false;
        hasSearched.value = false;
        destinations.value = [];
        trips.value = [];

        return;
    }

    isFetching.value = true;
    void fetchSuggestions(trimmedQuery.value);
}

function navigate(url: string): void {
    pendingRequest?.abort();
    isOpen.value = false;

    router.get(
        url,
        {},
        {
            onStart: () => {
                isNavigating.value = true;
            },
            onFinish: () => {
                isNavigating.value = false;
            },
        },
    );
}

function select(option: Option): void {
    if (option.kind === 'destination') {
        query.value = option.item.name;
        navigate(
            openTripsIndex({ query: { destination: option.item.name } }).url,
        );

        return;
    }

    navigate(openTripShow(option.item.id).url);
}

function submit(): void {
    if (isNavigating.value) {
        return;
    }

    const activeOption = options.value[activeIndex.value];

    if (showDropdown.value && activeOption) {
        select(activeOption);

        return;
    }

    const destination = trimmedQuery.value;

    navigate(openTripsIndex({ query: destination ? { destination } : {} }).url);
}

function moveActive(step: number): void {
    if (!showDropdown.value || options.value.length === 0) {
        isOpen.value = true;

        return;
    }

    const count = options.value.length;
    activeIndex.value = (activeIndex.value + step + count) % count;
}

/**
 * Split a label into pieces so the typed text can be emphasised.
 */
function highlight(text: string): { text: string; match: boolean }[] {
    const term = trimmedQuery.value;
    const start = term ? text.toLowerCase().indexOf(term.toLowerCase()) : -1;

    if (start === -1) {
        return [{ text, match: false }];
    }

    return [
        { text: text.slice(0, start), match: false },
        { text: text.slice(start, start + term.length), match: true },
        { text: text.slice(start + term.length), match: false },
    ].filter((part) => part.text !== '');
}

function optionId(index: number): string {
    return `home-search-option-${index}`;
}
</script>

<template>
    <div ref="container" class="relative max-w-xl">
        <form
            class="flex flex-col gap-2 rounded-2xl border border-white/15 bg-white/10 p-2 shadow-2xl shadow-black/20 backdrop-blur-md sm:flex-row"
            role="search"
            @submit.prevent="submit"
        >
            <label
                class="flex flex-1 items-center gap-3 rounded-xl bg-white px-4"
            >
                <Search class="size-5 shrink-0 text-slate-400" />
                <span class="sr-only">Search open trips by destination</span>
                <input
                    v-model="query"
                    type="search"
                    autocomplete="off"
                    placeholder="Where do you want to go?"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="home-search-listbox"
                    :aria-expanded="showDropdown"
                    :aria-activedescendant="
                        activeIndex >= 0 ? optionId(activeIndex) : undefined
                    "
                    :disabled="isNavigating"
                    class="h-12 w-full bg-transparent text-base text-slate-900 placeholder:text-slate-400 focus:outline-none disabled:opacity-60"
                    @input="onInput"
                    @focus="isOpen = true"
                    @keydown.down.prevent="moveActive(1)"
                    @keydown.up.prevent="moveActive(-1)"
                    @keydown.esc="isOpen = false"
                />
                <Spinner
                    v-if="isFetching"
                    class="size-4 shrink-0 text-slate-400"
                />
            </label>
            <Button
                type="submit"
                size="lg"
                :disabled="isNavigating"
                class="h-12 min-w-36 bg-teal-500 px-6 text-base font-semibold text-white hover:bg-teal-400 disabled:opacity-90"
            >
                <template v-if="isNavigating">
                    <Spinner class="size-4" />
                    Searching…
                </template>
                <template v-else>Find trips</template>
            </Button>
        </form>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <div
                v-if="showDropdown"
                id="home-search-listbox"
                role="listbox"
                class="absolute inset-x-0 top-full z-30 mt-2 max-h-[min(28rem,60vh)] overflow-y-auto rounded-2xl border border-border bg-popover text-popover-foreground shadow-2xl"
            >
                <template v-if="options.length">
                    <div v-if="destinations.length" class="p-2">
                        <p
                            class="px-3 pt-1 pb-2 text-[11px] font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            Destinations
                        </p>
                        <button
                            v-for="(destination, index) in destinations"
                            :id="optionId(index)"
                            :key="destination.label"
                            type="button"
                            role="option"
                            :aria-selected="activeIndex === index"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition"
                            :class="
                                activeIndex === index
                                    ? 'bg-accent text-accent-foreground'
                                    : 'hover:bg-accent/60'
                            "
                            @mouseenter="activeIndex = index"
                            @click="
                                select({
                                    kind: 'destination',
                                    key: destination.label,
                                    item: destination,
                                })
                            "
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"
                            >
                                <MapPin class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium"
                                >
                                    <template
                                        v-for="(part, partIndex) in highlight(
                                            destination.label,
                                        )"
                                        :key="partIndex"
                                    >
                                        <mark
                                            v-if="part.match"
                                            class="bg-transparent font-bold text-primary"
                                            >{{ part.text }}</mark
                                        >
                                        <template v-else>{{
                                            part.text
                                        }}</template>
                                    </template>
                                </span>
                            </span>
                            <span
                                class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground tabular-nums"
                            >
                                {{ destination.trip_count }}
                                {{
                                    destination.trip_count === 1
                                        ? 'trip'
                                        : 'trips'
                                }}
                            </span>
                        </button>
                    </div>

                    <div
                        v-if="trips.length"
                        class="border-t border-border/70 p-2"
                        :class="{ 'border-t-0': !destinations.length }"
                    >
                        <p
                            class="px-3 pt-1 pb-2 text-[11px] font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            Open trips
                        </p>
                        <button
                            v-for="(trip, tripIndex) in trips"
                            :id="optionId(destinations.length + tripIndex)"
                            :key="trip.id"
                            type="button"
                            role="option"
                            :aria-selected="
                                activeIndex === destinations.length + tripIndex
                            "
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition"
                            :class="
                                activeIndex === destinations.length + tripIndex
                                    ? 'bg-accent text-accent-foreground'
                                    : 'hover:bg-accent/60'
                            "
                            @mouseenter="
                                activeIndex = destinations.length + tripIndex
                            "
                            @click="
                                select({
                                    kind: 'trip',
                                    key: trip.id,
                                    item: trip,
                                })
                            "
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400"
                            >
                                <Compass class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium"
                                >
                                    <template
                                        v-for="(part, partIndex) in highlight(
                                            trip.title,
                                        )"
                                        :key="partIndex"
                                    >
                                        <mark
                                            v-if="part.match"
                                            class="bg-transparent font-bold text-primary"
                                            >{{ part.text }}</mark
                                        >
                                        <template v-else>{{
                                            part.text
                                        }}</template>
                                    </template>
                                </span>
                                <span
                                    v-if="trip.destination"
                                    class="block truncate text-xs text-muted-foreground"
                                >
                                    {{ trip.destination }}
                                </span>
                            </span>
                            <span
                                v-if="trip.start_date"
                                class="flex shrink-0 items-center gap-1 text-xs text-muted-foreground"
                            >
                                <CalendarDays class="size-3.5" />
                                {{ formatShortDate(trip.start_date) }}
                            </span>
                        </button>
                    </div>
                </template>

                <div v-else class="px-5 py-6 text-center">
                    <p class="text-sm font-medium">
                        No open trips match “{{ trimmedQuery }}” yet
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Press Enter to search all open trips anyway.
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>
