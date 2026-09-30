<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import TripPilotLogoMark from '@/components/logos/TripPilotLogoMark.vue';

/**
 * Centered overlay shown while navigating between pages. Only page visits
 * (GET, not prefetches or background requests) trigger it, and it waits a
 * moment before appearing so fast navigations don't flash.
 */
const SHOW_DELAY_MS = 150;

const isVisible = ref(false);
let showTimer: ReturnType<typeof setTimeout> | null = null;
const removeListeners: Array<() => void> = [];

function hide(): void {
    if (showTimer) {
        clearTimeout(showTimer);
        showTimer = null;
    }

    isVisible.value = false;
}

onMounted(() => {
    removeListeners.push(
        router.on('start', (event) => {
            const visit = event.detail.visit;

            if (visit.method !== 'get' || visit.prefetch || visit.async) {
                return;
            }

            hide();
            showTimer = setTimeout(() => {
                isVisible.value = true;
            }, SHOW_DELAY_MS);
        }),
        router.on('finish', hide),
    );
});

onBeforeUnmount(() => {
    hide();
    removeListeners.forEach((remove) => remove());
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="isVisible"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-background/50 backdrop-blur-[2px]"
            role="status"
            aria-live="polite"
        >
            <div
                class="flex flex-col items-center gap-3 rounded-2xl border border-border/70 bg-card/95 px-8 py-6 shadow-2xl"
            >
                <div class="relative flex size-16 items-center justify-center">
                    <span
                        class="absolute inset-0 animate-spin rounded-full border-[3px] border-primary/15 border-t-primary"
                    />
                    <TripPilotLogoMark class="size-9" />
                </div>
                <p class="text-sm font-medium text-muted-foreground">
                    Loading…
                </p>
            </div>
        </div>
    </Transition>
</template>
