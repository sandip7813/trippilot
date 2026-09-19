<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { TripPhase, TripPhaseCounts } from '@/types/trip';

const props = defineProps<{
    phase: TripPhase;
    counts: TripPhaseCounts;
    href: (phase: TripPhase) => string;
    only: string[];
}>();

const tabs = computed<{ key: TripPhase; label: string }[]>(() => [
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'ongoing', label: 'Ongoing' },
    { key: 'past', label: 'Past' },
]);
</script>

<template>
    <div class="inline-flex w-fit gap-1 rounded-lg bg-muted p-1" role="tablist">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="props.href(tab.key)"
            :only="props.only"
            preserve-state
            preserve-scroll
            role="tab"
            :aria-selected="phase === tab.key"
            :class="
                cn(
                    'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                    phase === tab.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground',
                )
            "
        >
            {{ tab.label }}
            <span
                class="rounded-full bg-primary/10 px-1.5 text-xs text-primary"
            >
                {{ counts[tab.key] }}
            </span>
        </Link>
    </div>
</template>
