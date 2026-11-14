<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { tripHubTabMeta } from '@/components/trip-hub/tripHubTabMeta';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type TripHubTab = {
    id: string;
    label: string;
    count?: number;
};

const props = defineProps<{
    tabs: TripHubTab[];
}>();

const active = defineModel<string>('active', { required: true });

const currentTab = computed(
    () =>
        props.tabs.find((tab) => tab.id === active.value)?.id ??
        props.tabs[0]?.id,
);

watch(
    currentTab,
    (id) => {
        if (id !== undefined && id !== active.value) {
            active.value = id;
        }
    },
    { immediate: true },
);

/**
 * Keeps the open tab in the URL hash so a refresh or shared link lands on the
 * same tab, without touching the history state Inertia relies on.
 */
onMounted(() => {
    const fromHash = window.location.hash.replace('#', '');

    if (props.tabs.some((tab) => tab.id === fromHash)) {
        active.value = fromHash;
    }
});

watch(active, (id) => {
    try {
        window.history.replaceState(window.history.state, '', `#${id}`);
    } catch {
        // The hash is only a convenience.
    }
});
</script>

<template>
    <div
        class="sticky top-2 z-30 rounded-2xl border border-white/70 bg-white/75 p-1.5 shadow-[0_1px_2px_hsl(222_47%_11%/0.04),0_10px_30px_-12px_hsl(262_83%_58%/0.25)] backdrop-blur-xl dark:border-border/60 dark:bg-card/75"
    >
        <div
            class="flex [scrollbar-width:none] gap-1 overflow-x-auto sm:flex-wrap sm:overflow-visible [&::-webkit-scrollbar]:hidden"
            role="tablist"
        >
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                role="tab"
                :aria-selected="currentTab === tab.id"
                :class="
                    cn(
                        'group relative inline-flex shrink-0 items-center gap-2 rounded-xl py-1.5 pr-3.5 pl-1.5 text-sm font-medium transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        currentTab === tab.id
                            ? [
                                  'bg-gradient-to-r text-white shadow-lg',
                                  tripHubTabMeta(tab.id).gradient,
                                  tripHubTabMeta(tab.id).glow,
                              ]
                            : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground',
                    )
                "
                @click="active = tab.id"
            >
                <span
                    :class="
                        cn(
                            'flex size-7 items-center justify-center rounded-lg transition-all duration-200',
                            currentTab === tab.id
                                ? 'bg-white/20 text-white'
                                : [
                                      'bg-muted/70 group-hover:scale-110 group-hover:bg-background',
                                      tripHubTabMeta(tab.id).tint,
                                  ],
                        )
                    "
                >
                    <component
                        :is="tripHubTabMeta(tab.id).icon"
                        class="size-4"
                    />
                </span>
                {{ tab.label }}
                <Badge
                    v-if="tab.count !== undefined && tab.count > 0"
                    variant="secondary"
                    :class="
                        cn(
                            'h-5 min-w-5 justify-center px-1.5 text-[10px]',
                            currentTab === tab.id &&
                                'border-transparent bg-white/25 text-white',
                        )
                    "
                >
                    {{ tab.count }}
                </Badge>
            </button>
        </div>
    </div>
</template>
