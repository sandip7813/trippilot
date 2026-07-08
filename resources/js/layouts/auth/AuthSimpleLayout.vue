<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, Sparkles } from '@lucide/vue';
import TripPilotBrand from '@/components/TripPilotBrand.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-2">
        <!-- Brand panel with banner image -->
        <div
            class="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between"
        >
            <img
                src="/images/hero-banner.jpg"
                alt=""
                class="absolute inset-0 size-full object-cover"
            />
            <div
                class="absolute inset-0 bg-gradient-to-b from-teal-950/75 via-violet-900/65 to-orange-900/70"
            />

            <div
                class="relative flex flex-1 flex-col justify-between p-10 text-white"
            >
                <Link :href="home()">
                    <TripPilotBrand variant="light" size="md" show-tagline />
                </Link>

                <div class="space-y-6">
                    <h2 class="text-3xl leading-tight font-bold tracking-tight">
                        Your next adventure starts here.
                    </h2>
                    <p class="max-w-md leading-relaxed text-white/80">
                        Build AI-powered itineraries, map road trips, and get
                        weather-aware recommendations — all in one place.
                    </p>
                    <ul class="space-y-3 text-sm text-white/90">
                        <li class="flex items-center gap-2">
                            <Sparkles class="size-4 shrink-0 text-teal-300" />
                            Smart day-by-day plans with Gemini
                        </li>
                        <li class="flex items-center gap-2">
                            <MapPin class="size-4 shrink-0 text-teal-300" />
                            Maps and routes powered by Geoapify
                        </li>
                    </ul>
                </div>

                <p class="text-sm text-white/50">
                    &copy; {{ new Date().getFullYear() }} TripPilot
                </p>
            </div>
        </div>

        <!-- Form panel -->
        <div
            class="app-page-bg flex flex-col items-center justify-center p-6 md:p-10"
        >
            <div class="w-full max-w-sm">
                <div class="mb-8 flex flex-col items-center gap-4 lg:hidden">
                    <Link :href="home()">
                        <TripPilotBrand size="md" show-tagline />
                    </Link>
                </div>

                <div class="mb-8 space-y-2 text-center lg:text-left">
                    <h1 class="text-2xl font-bold tracking-tight">
                        <span class="brand-gradient-text">{{ title }}</span>
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
