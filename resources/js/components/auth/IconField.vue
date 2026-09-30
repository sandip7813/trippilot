<script setup lang="ts">
import type { Component } from 'vue';

/**
 * Wraps an auth form input with a leading icon. The input itself needs
 * `pl-10` so its text clears the icon.
 */
defineProps<{
    icon: Component;
}>();
</script>

<template>
    <div class="relative">
        <component
            :is="icon"
            class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
        <slot />
    </div>
</template>
