<script setup lang="ts">
import { contentProseClass } from '@/lib/contentProse';

/**
 * Displays server-rendered Markdown. The HTML comes from
 * ContentPageRenderer, which strips raw HTML and unsafe links.
 */
defineProps<{
    html: string;
}>();
</script>

<template>
    <div :class="contentProseClass" v-html="html" />
</template>
