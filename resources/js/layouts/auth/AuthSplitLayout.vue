<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import TripPilotBrand from '@/components/TripPilotBrand.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col overflow-hidden p-10 text-white lg:flex"
        >
            <img
                src="/images/hero-banner.jpg"
                alt=""
                class="absolute inset-0 size-full object-cover"
            />
            <div
                class="absolute inset-0 bg-gradient-to-br from-teal-700/90 via-violet-700/75 to-orange-600/70"
            />
            <Link :href="home()" class="relative z-20 flex items-center">
                <TripPilotBrand variant="light" size="md" show-tagline />
            </Link>
            <div class="relative z-20 mt-auto max-w-md">
                <div
                    class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-sm backdrop-blur-sm"
                >
                    <Sparkles class="size-4 text-amber-200" />
                    AI-powered planning
                </div>
                <blockquote class="space-y-3">
                    <p class="text-2xl leading-snug font-semibold">
                        &ldquo;Turn your travel dreams into day-by-day plans in
                        minutes.&rdquo;
                    </p>
                    <footer class="text-sm text-white/75">
                        &mdash; {{ name }}
                    </footer>
                </blockquote>
            </div>
        </div>
        <div class="app-page-bg lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[380px]"
            >
                <div class="flex flex-col space-y-2 text-center lg:text-left">
                    <h1 v-if="title" class="text-2xl font-bold tracking-tight">
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
