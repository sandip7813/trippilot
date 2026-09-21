<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
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
        class="flex gap-1 overflow-x-auto rounded-xl border border-border/60 bg-muted/40 p-1"
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
                    'inline-flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                    currentTab === tab.id
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                )
            "
            @click="active = tab.id"
        >
            {{ tab.label }}
            <Badge
                v-if="tab.count !== undefined && tab.count > 0"
                variant="secondary"
                class="h-5 min-w-5 justify-center px-1.5 text-[10px]"
            >
                {{ tab.count }}
            </Badge>
        </button>
    </div>
</template>
