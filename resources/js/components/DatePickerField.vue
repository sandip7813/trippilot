<script setup lang="ts">
import { Calendar, ChevronLeft, ChevronRight } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';
import {
    buildIsoDate,
    compareIsoDates,
    daysInMonth,
    isoToday,
    isoToDisplay,
    monthLabel,
    parseIsoDate,
} from '@/lib/dates';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id: string;
        min?: string;
        placeholder?: string;
    }>(),
    {
        placeholder: 'DD/MM/YYYY',
    },
);

const model = defineModel<string>({ default: '' });

const open = ref(false);
const rootRef = ref<HTMLElement | null>(null);

const viewYear = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth() + 1);

onClickOutside(rootRef, () => {
    open.value = false;
});

const displayValue = computed(() => isoToDisplay(model.value));

const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

type CalendarCell = {
    iso: string;
    day: number;
    disabled: boolean;
    selected: boolean;
    today: boolean;
} | null;

function syncViewToModel(): void {
    const parsed = model.value ? parseIsoDate(model.value) : null;
    const minParsed = props.min ? parseIsoDate(props.min) : null;
    const todayParsed = parseIsoDate(isoToday());

    if (parsed) {
        viewYear.value = parsed.year;
        viewMonth.value = parsed.month;
    } else if (minParsed) {
        viewYear.value = minParsed.year;
        viewMonth.value = minParsed.month;
    } else if (todayParsed) {
        viewYear.value = todayParsed.year;
        viewMonth.value = todayParsed.month;
    }
}

function toggleOpen(): void {
    if (!open.value) {
        syncViewToModel();
    }

    open.value = !open.value;
}

function isDisabled(iso: string): boolean {
    return props.min ? compareIsoDates(iso, props.min) < 0 : false;
}

function selectDate(iso: string): void {
    model.value = iso;
    open.value = false;
}

const calendarWeeks = computed((): CalendarCell[][] => {
    const year = viewYear.value;
    const month = viewMonth.value;
    const firstWeekday = new Date(year, month - 1, 1).getDay();
    const totalDays = daysInMonth(year, month);
    const today = isoToday();

    const cells: CalendarCell[] = [];

    for (let i = 0; i < firstWeekday; i++) {
        cells.push(null);
    }

    for (let day = 1; day <= totalDays; day++) {
        const iso = buildIsoDate(year, month, day);

        cells.push({
            iso,
            day,
            disabled: isDisabled(iso),
            selected: iso === model.value,
            today: iso === today,
        });
    }

    const weeks: CalendarCell[][] = [];

    for (let i = 0; i < cells.length; i += 7) {
        weeks.push(cells.slice(i, i + 7));
    }

    const lastWeek = weeks.at(-1);

    if (lastWeek && lastWeek.length < 7) {
        while (lastWeek.length < 7) {
            lastWeek.push(null);
        }
    }

    return weeks;
});

const headerLabel = computed(() => monthLabel(viewYear.value, viewMonth.value));

const canSelectToday = computed(() => !isDisabled(isoToday()));

function previousMonth(): void {
    if (viewMonth.value === 1) {
        viewYear.value -= 1;
        viewMonth.value = 12;
    } else {
        viewMonth.value -= 1;
    }
}

function nextMonth(): void {
    if (viewMonth.value === 12) {
        viewYear.value += 1;
        viewMonth.value = 1;
    } else {
        viewMonth.value += 1;
    }
}

function clearSelection(): void {
    model.value = '';
    open.value = false;
}

function selectToday(): void {
    const today = isoToday();

    if (!isDisabled(today)) {
        selectDate(today);
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            :id="id"
            type="button"
            :aria-labelledby="`${id}-label`"
            :aria-expanded="open"
            aria-haspopup="dialog"
            class="flex h-9 w-full cursor-pointer items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
            @click="toggleOpen"
            @keydown="onKeydown"
        >
            <span :class="displayValue ? '' : 'text-muted-foreground'">
                {{ displayValue || placeholder }}
            </span>
            <Calendar class="size-4 shrink-0 text-muted-foreground" />
        </button>

        <div
            v-if="open"
            role="dialog"
            aria-modal="true"
            :aria-label="`Choose date for ${id}`"
            class="absolute top-full right-0 left-0 z-50 mt-1 w-full min-w-64 rounded-md border border-border bg-popover p-3 text-popover-foreground shadow-lg"
        >
            <div class="mb-3 flex items-center justify-between">
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md hover:bg-accent"
                    aria-label="Previous month"
                    @click="previousMonth"
                >
                    <ChevronLeft class="size-4" />
                </button>
                <span class="text-sm font-medium">{{ headerLabel }}</span>
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md hover:bg-accent"
                    aria-label="Next month"
                    @click="nextMonth"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                <div
                    v-for="day in weekDays"
                    :key="day"
                    class="py-1 font-medium"
                >
                    {{ day }}
                </div>
            </div>

            <div class="mt-1 grid gap-1">
                <div
                    v-for="(week, weekIndex) in calendarWeeks"
                    :key="weekIndex"
                    class="grid grid-cols-7 gap-1"
                >
                    <template
                        v-for="(cell, cellIndex) in week"
                        :key="cellIndex"
                    >
                        <div
                            v-if="!cell"
                            class="size-8"
                        />
                        <button
                            v-else
                            type="button"
                            :disabled="cell.disabled"
                            :aria-label="cell.iso"
                            :aria-selected="cell.selected"
                            :class="cn(
                                'inline-flex size-8 items-center justify-center rounded-md text-sm transition-colors',
                                cell.disabled && 'cursor-not-allowed text-muted-foreground/40',
                                !cell.disabled && !cell.selected && 'hover:bg-accent',
                                cell.selected && 'bg-primary text-primary-foreground hover:bg-primary',
                                cell.today && !cell.selected && 'border border-primary font-medium',
                            )"
                            @click="selectDate(cell.iso)"
                        >
                            {{ cell.day }}
                        </button>
                    </template>
                </div>
            </div>

            <div class="mt-3 flex justify-between border-t border-border pt-2">
                <button
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="clearSelection"
                >
                    Clear
                </button>
                <button
                    type="button"
                    class="text-xs text-primary hover:underline disabled:pointer-events-none disabled:opacity-40"
                    :disabled="!canSelectToday"
                    @click="selectToday"
                >
                    Today
                </button>
            </div>
        </div>
    </div>
</template>
