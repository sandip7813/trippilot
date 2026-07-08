<script setup lang="ts">
import TripPilotLogoMark from '@/components/logos/TripPilotLogoMark.vue';
</script>

<template>
    <div class="flex items-center gap-2.5">
        <div class="flex size-8 shrink-0 items-center justify-center">
            <TripPilotLogoMark class="size-8" />
        </div>
        <div
            class="grid flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden"
        >
            <span class="truncate font-semibold tracking-tight">TripPilot</span>
            <span class="truncate text-xs text-muted-foreground"
                >Plan smarter</span
            >
        </div>
    </div>
</template>
