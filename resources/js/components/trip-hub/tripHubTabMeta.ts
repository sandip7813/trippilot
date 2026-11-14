import {
    BedDouble,
    Bus,
    CalendarDays,
    CloudSun,
    Globe,
    LayoutDashboard,
    NotebookPen,
    Plane,
    Route,
    Sparkles,
    TrainFront,
    Wallet,
} from '@lucide/vue';
import type { Component } from 'vue';

/**
 * Visual identity for each trip hub tab. Class strings are kept whole so
 * Tailwind can detect them at build time.
 */
export type TripHubTabMeta = {
    icon: Component;
    /** Gradient used for the active tab pill and the panel header icon tile. */
    gradient: string;
    /** Icon colour while the tab is inactive. */
    tint: string;
    /** Coloured shadow under the active pill. */
    glow: string;
    /** Soft wash behind the panel header. */
    wash: string;
    description: string;
};

const tabMeta: Record<string, TripHubTabMeta> = {
    overview: {
        icon: LayoutDashboard,
        gradient: 'from-teal-500 to-cyan-500',
        tint: 'text-teal-600 dark:text-teal-400',
        glow: 'shadow-teal-500/30',
        wash: 'from-teal-500/15 via-cyan-500/5 to-transparent',
        description: 'Dates, travelers, budget and your route at a glance.',
    },
    route: {
        icon: Route,
        gradient: 'from-teal-500 to-emerald-500',
        tint: 'text-teal-600 dark:text-teal-400',
        glow: 'shadow-teal-500/30',
        wash: 'from-teal-500/15 via-emerald-500/5 to-transparent',
        description: 'Your drive on the map, with stops, breaks and amenities.',
    },
    itinerary: {
        icon: CalendarDays,
        gradient: 'from-violet-500 to-fuchsia-500',
        tint: 'text-violet-600 dark:text-violet-400',
        glow: 'shadow-violet-500/30',
        wash: 'from-violet-500/15 via-fuchsia-500/5 to-transparent',
        description: 'A day-by-day plan for everything you want to do.',
    },
    weather: {
        icon: CloudSun,
        gradient: 'from-sky-500 to-indigo-500',
        tint: 'text-sky-600 dark:text-sky-400',
        glow: 'shadow-sky-500/30',
        wash: 'from-sky-500/15 via-indigo-500/5 to-transparent',
        description: 'Forecasts for every stop along the way.',
    },
    'open-trip': {
        icon: Globe,
        gradient: 'from-emerald-500 to-teal-500',
        tint: 'text-emerald-600 dark:text-emerald-400',
        glow: 'shadow-emerald-500/30',
        wash: 'from-emerald-500/15 via-teal-500/5 to-transparent',
        description: 'How your published trip looks to fellow travelers.',
    },
    trains: {
        icon: TrainFront,
        gradient: 'from-orange-500 to-rose-500',
        tint: 'text-orange-600 dark:text-orange-400',
        glow: 'shadow-orange-500/30',
        wash: 'from-orange-500/15 via-rose-500/5 to-transparent',
        description: 'Trains between your stops, with timings and halts.',
    },
    hotels: {
        icon: BedDouble,
        gradient: 'from-indigo-500 to-violet-500',
        tint: 'text-indigo-600 dark:text-indigo-400',
        glow: 'shadow-indigo-500/30',
        wash: 'from-indigo-500/15 via-violet-500/5 to-transparent',
        description: 'Places to stay near each destination.',
    },
    airports: {
        icon: Plane,
        gradient: 'from-sky-500 to-blue-600',
        tint: 'text-sky-600 dark:text-sky-400',
        glow: 'shadow-sky-500/30',
        wash: 'from-sky-500/15 via-blue-500/5 to-transparent',
        description: 'Nearest airports and how far they are.',
    },
    'public-transport': {
        icon: Bus,
        gradient: 'from-lime-500 to-emerald-600',
        tint: 'text-emerald-600 dark:text-emerald-400',
        glow: 'shadow-emerald-500/30',
        wash: 'from-lime-500/15 via-emerald-500/5 to-transparent',
        description: 'Buses, metros and local ways to get around.',
    },
    expenses: {
        icon: Wallet,
        gradient: 'from-amber-500 to-orange-500',
        tint: 'text-amber-600 dark:text-amber-400',
        glow: 'shadow-amber-500/30',
        wash: 'from-amber-500/15 via-orange-500/5 to-transparent',
        description: 'Track spending and settle up with your group.',
    },
    assistant: {
        icon: Sparkles,
        gradient: 'from-fuchsia-500 to-violet-600',
        tint: 'text-fuchsia-600 dark:text-fuchsia-400',
        glow: 'shadow-fuchsia-500/30',
        wash: 'from-fuchsia-500/15 via-violet-500/5 to-transparent',
        description: 'Ask anything about this trip and get instant answers.',
    },
    notes: {
        icon: NotebookPen,
        gradient: 'from-rose-500 to-pink-500',
        tint: 'text-rose-600 dark:text-rose-400',
        glow: 'shadow-rose-500/30',
        wash: 'from-rose-500/15 via-pink-500/5 to-transparent',
        description: 'Your notes, packing list and budget breakdown.',
    },
};

const fallbackMeta: TripHubTabMeta = {
    icon: LayoutDashboard,
    gradient: 'from-teal-500 to-violet-500',
    tint: 'text-primary',
    glow: 'shadow-primary/30',
    wash: 'from-primary/15 via-primary/5 to-transparent',
    description: '',
};

export function tripHubTabMeta(id: string): TripHubTabMeta {
    return tabMeta[id] ?? fallbackMeta;
}
