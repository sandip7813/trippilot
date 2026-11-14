<script setup lang="ts">
import { computed } from 'vue';
import { tripHubTabMeta } from '@/components/trip-hub/tripHubTabMeta';
import { cn } from '@/lib/utils';

const props = defineProps<{
    tabId: string;
    title: string;
    description?: string;
}>();

const meta = computed(() => tripHubTabMeta(props.tabId));
</script>

<template>
    <div
        :class="
            cn(
                'relative overflow-hidden rounded-2xl border border-white/70 bg-gradient-to-r p-4 shadow-sm sm:p-5 dark:border-border/60',
                meta.wash,
            )
        "
    >
        <div
            :class="
                cn(
                    'pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-gradient-to-br opacity-20 blur-3xl',
                    meta.gradient,
                )
            "
            aria-hidden="true"
        />
        <div class="relative flex items-center gap-4">
            <span
                :class="
                    cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg ring-4 ring-white/60 dark:ring-white/10',
                        meta.gradient,
                        meta.glow,
                    )
                "
            >
                <component :is="meta.icon" class="size-6" />
            </span>
            <div class="min-w-0 flex-1">
                <h2
                    class="text-lg font-bold tracking-tight text-foreground sm:text-xl"
                >
                    {{ title }}
                </h2>
                <p
                    v-if="description ?? meta.description"
                    class="text-sm text-muted-foreground"
                >
                    {{ description ?? meta.description }}
                </p>
            </div>
            <div v-if="$slots.default" class="shrink-0">
                <slot />
            </div>
        </div>
    </div>
</template>
