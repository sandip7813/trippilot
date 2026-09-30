<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import TripPilotBrand from '@/components/TripPilotBrand.vue';
import { cn } from '@/lib/utils';
import { dashboard, home, login, register } from '@/routes';
import { index as openTripsIndex } from '@/routes/open-trips';
import { index as tripsIndex } from '@/routes/trips';

/**
 * Shared footer for the guest-reachable pages (home, Discover, open trip
 * overview and the auth screens).
 */
withDefaults(
    defineProps<{
        containerClass?: HTMLAttributes['class'];
    }>(),
    { containerClass: 'max-w-7xl' },
);

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const links = computed(() => [
    { title: 'Home', href: home() },
    { title: 'Discover', href: openTripsIndex() },
    ...(user.value
        ? [
              { title: 'Dashboard', href: dashboard() },
              { title: 'My trips', href: tripsIndex() },
          ]
        : [
              { title: 'Log in', href: login() },
              { title: 'Sign up', href: register() },
          ]),
]);
</script>

<template>
    <footer class="border-t border-border/60 bg-muted/30">
        <div
            :class="
                cn(
                    'mx-auto flex flex-col items-center justify-between gap-6 px-4 py-10 sm:flex-row sm:px-6 lg:px-8',
                    containerClass,
                )
            "
        >
            <Link :href="home()" aria-label="TripPilot home">
                <TripPilotBrand size="sm" show-tagline />
            </Link>
            <nav
                class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-muted-foreground"
            >
                <Link
                    v-for="link in links"
                    :key="link.title"
                    :href="link.href"
                    class="transition-colors hover:text-foreground"
                >
                    {{ link.title }}
                </Link>
            </nav>
            <p class="text-sm text-muted-foreground">
                &copy; {{ new Date().getFullYear() }} TripPilot
            </p>
        </div>
    </footer>
</template>
