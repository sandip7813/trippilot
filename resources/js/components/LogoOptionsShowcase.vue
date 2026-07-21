<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripPilotLogoMark from '@/components/logos/TripPilotLogoMark.vue';
import { ACTIVE_LOGO, LOGO_OPTIONS } from '@/config/brand';
import type { LogoVariant } from '@/config/brand';

const props = withDefaults(
    defineProps<{
        selectable?: boolean;
        selected?: LogoVariant;
    }>(),
    {
        selectable: false,
    },
);

const page = usePage();

const selectedLogo = ref<LogoVariant>(props.selected ?? ACTIVE_LOGO);

watch(
    () => props.selected,
    (value) => {
        if (value) {
            selectedLogo.value = value;
        }
    },
);

const activeLogo = computed((): LogoVariant => {
    if (props.selectable) {
        return selectedLogo.value;
    }

    const shared = page.props.brand?.logo;

    if (shared && LOGO_OPTIONS.some((option) => option.id === shared)) {
        return shared as LogoVariant;
    }

    return ACTIVE_LOGO;
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <label
            v-for="option in LOGO_OPTIONS"
            :key="option.id"
            class="relative flex cursor-pointer flex-col items-center rounded-xl border p-6 transition-colors"
            :class="
                option.id === activeLogo
                    ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                    : 'border-border/60 bg-muted/20 hover:border-border'
            "
        >
            <input
                v-if="selectable"
                v-model="selectedLogo"
                type="radio"
                name="logo"
                :value="option.id"
                class="sr-only"
            />

            <span
                v-if="option.id === activeLogo"
                class="absolute top-3 right-3 flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground uppercase"
            >
                <Check class="size-3" />
                {{ selectable ? 'Selected' : 'Active' }}
            </span>

            <div
                class="flex size-20 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-border/40"
            >
                <TripPilotLogoMark :logo="option.id" class="size-14" />
            </div>

            <div
                class="mt-3 flex size-20 items-center justify-center rounded-xl bg-teal-950 shadow-sm"
            >
                <TripPilotLogoMark
                    :logo="option.id"
                    variant="light"
                    class="size-14"
                />
            </div>

            <h3 class="mt-4 text-sm font-semibold">{{ option.label }}</h3>
            <p class="mt-1 text-center text-xs text-muted-foreground">
                {{ option.description }}
            </p>
            <a
                v-if="!selectable"
                :href="option.file"
                target="_blank"
                class="mt-3 text-xs font-medium text-primary hover:underline"
                @click.stop
            >
                Download SVG
            </a>
        </label>
    </div>
    <p v-if="selectable" class="mt-4 text-xs text-muted-foreground">
        Choose a logo mark for the sidebar, auth pages, and landing page.
        Save settings to apply it across the app.
    </p>
    <p v-else class="mt-4 text-xs text-muted-foreground">
        Set
        <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px]"
            >TRIPPILOT_LOGO</code
        >
        in your
        <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px]"
            >.env</code
        >
        file to one of:
        <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px]"
            >plane | compass | pin | globe | monogram</code
        >
        then run
        <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px]"
            >php artisan config:clear</code
        >.
    </p>
</template>
