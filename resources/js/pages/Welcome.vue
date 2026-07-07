<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bot,
    CheckCircle2,
    CloudSun,
    Map,
    Route,
    Sparkles,
    Star,
} from '@lucide/vue';
import TripPilotBrand from '@/components/TripPilotBrand.vue';
import { Button } from '@/components/ui/button';
import { featureIconAccent } from '@/lib/card-accents';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

const destinations = [
    {
        name: 'Coastal escapes',
        location: 'Mediterranean',
        image: '/images/destination-santorini.jpg',
        tag: 'Popular',
    },
    {
        name: 'Open road',
        location: 'Mountain routes',
        image: '/images/destination-roadtrip.jpg',
        tag: 'Road trip',
    },
    {
        name: 'Island paradise',
        location: 'Tropical beaches',
        image: '/images/destination-beach.jpg',
        tag: 'Relax',
    },
];

const steps = [
    {
        step: '01',
        title: 'Tell us your dream',
        description: 'Share destination, dates, budget, and travel style in plain language.',
    },
    {
        step: '02',
        title: 'AI builds your plan',
        description: 'Gemini crafts a day-by-day itinerary with activities, meals, and tips.',
    },
    {
        step: '03',
        title: 'Refine & go',
        description: 'Adjust with the chat assistant, check weather, and hit the road.',
    },
];

const features = [
    { icon: Sparkles, title: 'AI itineraries', text: 'Personalized plans in seconds' },
    { icon: Map, title: 'Interactive maps', text: 'Geoapify-powered exploration' },
    { icon: Route, title: 'Road trip routes', text: 'Stops, timing, and scenic drives' },
    { icon: CloudSun, title: 'Weather aware', text: 'Forecasts for every leg' },
    { icon: Bot, title: 'Travel assistant', text: 'Chat to refine your plans' },
    { icon: Star, title: 'One workspace', text: 'Trips and routes together' },
];

const stats = [
    { value: 'AI-powered', label: 'Smart planning' },
    { value: 'Maps + Weather', label: 'Real-world data' },
    { value: 'Free to start', label: 'No credit card' },
];
</script>

<template>
    <Head title="Welcome">
        <meta
            name="description"
            content="TripPilot — AI-powered trip planning. Create itineraries, map road trips, and explore the world smarter."
        />
    </Head>

    <div class="min-h-screen bg-background text-foreground">
        <!-- ═══ HERO with banner image ═══ -->
        <section class="relative min-h-[92vh] overflow-hidden">
            <!-- Background image -->
            <img
                src="/images/hero-banner.jpg"
                alt=""
                class="absolute inset-0 size-full object-cover"
                fetchpriority="high"
            />
            <!-- Overlays -->
            <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/80" />
            <div
                class="absolute inset-0 bg-gradient-to-r from-teal-950/50 via-transparent to-sky-950/30"
            />

            <!-- Nav -->
            <header class="relative z-20">
                <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-8">
                    <Link :href="dashboard()">
                        <TripPilotBrand variant="light" size="md" show-tagline />
                    </Link>
                    <nav class="flex items-center gap-2">
                        <template v-if="$page.props.auth.user">
                            <Button
                                variant="ghost"
                                as-child
                                class="text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link :href="dashboard()">Dashboard</Link>
                            </Button>
                        </template>
                        <template v-else>
                            <Button
                                variant="ghost"
                                as-child
                                class="hidden text-white hover:bg-white/10 hover:text-white sm:inline-flex"
                            >
                                <Link :href="login()">Log in</Link>
                            </Button>
                            <Button
                                as-child
                                class="bg-white text-teal-800 shadow-lg hover:bg-white/90"
                            >
                                <Link :href="register()">Get started free</Link>
                            </Button>
                        </template>
                    </nav>
                </div>
            </header>

            <!-- Hero content -->
            <div
                class="relative z-10 mx-auto flex min-h-[calc(92vh-5rem)] max-w-7xl flex-col justify-center px-6 pb-16 pt-8 lg:px-8"
            >
                <div class="max-w-2xl">
                    <div
                        class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-medium text-white backdrop-blur-sm"
                    >
                        <Sparkles class="size-4 text-sky-300" />
                        AI-powered trip planning
                    </div>
                    <h1
                        class="text-4xl font-extrabold leading-[1.1] tracking-tight text-white text-balance sm:text-5xl lg:text-6xl xl:text-7xl"
                    >
                        Your next adventure,
                        <span
                            class="bg-gradient-to-r from-teal-300 to-sky-300 bg-clip-text text-transparent"
                        >
                            perfectly planned
                        </span>
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/80 text-pretty sm:text-xl">
                        From weekend getaways to cross-country road trips — TripPilot turns your
                        ideas into beautiful itineraries with AI, maps, and live weather.
                    </p>
                    <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Button
                            size="lg"
                            as-child
                            class="h-12 bg-teal-500 px-8 text-base font-semibold text-white shadow-xl shadow-teal-900/30 hover:bg-teal-400"
                        >
                            <Link :href="$page.props.auth.user ? dashboard() : register()">
                                {{
                                    $page.props.auth.user
                                        ? 'Go to dashboard'
                                        : 'Start planning — it\'s free'
                                }}
                                <ArrowRight class="ml-2 size-5" />
                            </Link>
                        </Button>
                        <Button
                            v-if="!$page.props.auth.user"
                            size="lg"
                            variant="outline"
                            as-child
                            class="h-12 border-white/30 bg-white/5 px-8 text-base text-white backdrop-blur-sm hover:bg-white/15 hover:text-white"
                        >
                            <Link :href="login()">Sign in</Link>
                        </Button>
                    </div>

                    <!-- Trust strip -->
                    <div class="mt-12 flex flex-wrap gap-6 border-t border-white/15 pt-8">
                        <div
                            v-for="stat in stats"
                            :key="stat.label"
                            class="flex items-center gap-2 text-white/90"
                        >
                            <CheckCircle2 class="size-4 shrink-0 text-teal-400" />
                            <div>
                                <p class="text-sm font-semibold">{{ stat.value }}</p>
                                <p class="text-xs text-white/60">{{ stat.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scroll hint -->
            <div class="absolute bottom-6 left-1/2 z-10 -translate-x-1/2">
                <div class="flex flex-col items-center gap-1 text-white/50">
                    <span class="text-xs tracking-widest uppercase">Explore</span>
                    <div class="h-8 w-px animate-pulse bg-gradient-to-b from-white/50 to-transparent" />
                </div>
            </div>
        </section>

        <!-- ═══ DESTINATIONS showcase ═══ -->
        <section class="app-page-bg py-24">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto mb-14 max-w-2xl text-center">
                    <p class="text-sm font-semibold tracking-widest text-primary uppercase">
                        Inspiration
                    </p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        <span class="brand-gradient-text">Where will you go next?</span>
                    </h2>
                    <p class="mt-4 text-muted-foreground">
                        Whether it's ancient cities, open highways, or hidden beaches — plan every
                        detail before you pack.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <article
                        v-for="(dest, index) in destinations"
                        :key="dest.name"
                        class="group relative overflow-hidden rounded-2xl shadow-lg"
                        :class="index === 0 ? 'md:row-span-1' : ''"
                    >
                        <img
                            :src="dest.image"
                            :alt="dest.name"
                            class="aspect-[4/5] size-full object-cover transition-transform duration-700 group-hover:scale-105 md:aspect-[3/4]"
                            loading="lazy"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"
                        />
                        <div class="absolute inset-x-0 bottom-0 p-6">
                            <span
                                class="mb-3 inline-block rounded-full bg-white/20 px-3 py-0.5 text-xs font-medium text-white backdrop-blur-sm"
                            >
                                {{ dest.tag }}
                            </span>
                            <h3 class="text-xl font-bold text-white">{{ dest.name }}</h3>
                            <p class="mt-1 text-sm text-white/70">{{ dest.location }}</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- ═══ HOW IT WORKS ═══ -->
        <section class="border-y border-border/60 bg-gradient-to-br from-teal-500/5 via-violet-500/5 to-orange-500/5 py-24">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid items-center gap-16 lg:grid-cols-2">
                    <!-- Image side -->
                    <div class="relative overflow-hidden rounded-2xl shadow-2xl">
                        <img
                            src="/images/destination-roadtrip.jpg"
                            alt="Scenic road trip"
                            class="aspect-[4/3] size-full object-cover"
                            loading="lazy"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-tr from-teal-900/40 to-transparent"
                        />
                        <div
                            class="absolute bottom-6 left-6 rounded-xl border border-white/20 bg-black/40 p-4 backdrop-blur-md"
                        >
                            <p class="text-xs font-medium tracking-wider text-teal-300 uppercase">
                                Powered by AI
                            </p>
                            <p class="mt-1 text-lg font-semibold text-white">
                                Plans in minutes, not hours
                            </p>
                        </div>
                    </div>

                    <!-- Steps -->
                    <div>
                        <p class="text-sm font-semibold tracking-widest text-primary uppercase">
                            How it works
                        </p>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                            Three steps to your perfect trip
                        </h2>
                        <ol class="mt-10 space-y-8">
                            <li
                                v-for="item in steps"
                                :key="item.step"
                                class="flex gap-5"
                            >
                                <span
                                    class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary"
                                >
                                    {{ item.step }}
                                </span>
                                <div>
                                    <h3 class="font-semibold">{{ item.title }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                                        {{ item.description }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ FEATURES grid ═══ -->
        <section class="py-24">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto mb-14 max-w-2xl text-center">
                    <p class="text-sm font-semibold tracking-widest text-primary uppercase">
                        Features
                    </p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        Everything a traveler needs
                    </h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(feature, index) in features"
                        :key="feature.title"
                        class="card-vibrant group flex items-start gap-4 p-6"
                    >
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-lg"
                            :class="[featureIconAccent(index), index % 2 === 0 ? 'shadow-teal-500/25' : 'shadow-violet-500/25']"
                        >
                            <component :is="feature.icon" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ feature.title }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ feature.text }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ CTA with banner image ═══ -->
        <section class="px-6 pb-24 lg:px-8">
            <div class="relative mx-auto max-w-7xl overflow-hidden rounded-3xl shadow-2xl">
                <img
                    src="/images/destination-beach.jpg"
                    alt=""
                    class="absolute inset-0 size-full object-cover"
                    loading="lazy"
                />
                <div class="absolute inset-0 bg-gradient-to-r from-teal-950/90 via-teal-900/75 to-teal-950/60" />
                <div class="relative px-8 py-20 text-center md:px-16 md:py-24">
                    <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl md:text-5xl">
                        Ready to explore the world?
                    </h2>
                    <p class="mx-auto mt-4 max-w-lg text-lg text-white/80">
                        Join TripPilot and turn your travel dreams into detailed, actionable plans
                        — completely free to get started.
                    </p>
                    <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Button
                            size="lg"
                            as-child
                            class="h-12 bg-white px-8 text-base font-semibold text-teal-800 shadow-xl hover:bg-white/90"
                        >
                            <Link :href="$page.props.auth.user ? dashboard() : register()">
                                {{
                                    $page.props.auth.user
                                        ? 'Open dashboard'
                                        : 'Create your free account'
                                }}
                                <ArrowRight class="ml-2 size-5" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ FOOTER ═══ -->
        <footer class="border-t border-border/60 bg-muted/30">
            <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <TripPilotBrand size="sm" show-tagline />
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} TripPilot. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
