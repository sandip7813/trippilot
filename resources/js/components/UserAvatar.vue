<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import TripPilotLogoMark from '@/components/logos/TripPilotLogoMark.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

/**
 * Shows a user's profile picture. When none was uploaded (or the image
 * fails to load) the portal logo is shown in its place.
 */
const props = defineProps<{
    src?: string | null;
    name?: string | null;
    class?: HTMLAttributes['class'];
    fallbackClass?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Avatar :class="cn('size-8 overflow-hidden rounded-full', props.class)">
        <AvatarImage
            v-if="src"
            :src="src"
            :alt="name ?? 'Profile picture'"
            class="object-cover"
        />
        <AvatarFallback
            :class="
                cn(
                    'rounded-[inherit] bg-white ring-1 ring-cyan-100 ring-inset dark:bg-slate-900 dark:ring-cyan-500/20',
                    fallbackClass,
                )
            "
            :aria-label="name ?? 'Profile picture'"
        >
            <TripPilotLogoMark class="size-[70%]" />
        </AvatarFallback>
    </Avatar>
</template>
