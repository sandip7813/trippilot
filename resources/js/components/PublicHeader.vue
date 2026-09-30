<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Compass,
    House,
    LayoutGrid,
    LogIn,
    LogOut,
    Mail,
    Menu,
    UserPlus,
} from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed, ref } from 'vue';
import TripPilotBrand from '@/components/TripPilotBrand.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn } from '@/lib/utils';
import { contact, dashboard, home, login, logout, register } from '@/routes';
import { index as openTripsIndex } from '@/routes/open-trips';

/**
 * Shared header for the guest-reachable pages (home, Discover, open trip
 * overview). `overlay` sits transparently on top of a hero image.
 */
const props = withDefaults(
    defineProps<{
        variant?: 'solid' | 'overlay';
        containerClass?: HTMLAttributes['class'];
    }>(),
    { variant: 'solid', containerClass: 'max-w-7xl' },
);

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const { currentUrl, isCurrentUrl } = useCurrentUrl();
const mobileMenuOpen = ref(false);

const isOverlay = computed(() => props.variant === 'overlay');

const navItems = computed(() => [
    { title: 'Home', href: home(), icon: House, active: isCurrentUrl(home()) },
    {
        title: 'Discover trips',
        href: openTripsIndex(),
        icon: Compass,
        active: currentUrl.value.startsWith(openTripsIndex().url),
    },
]);

/** Kept apart from `navItems` so it renders last, after the account actions. */
const contactItem = computed(() => ({
    title: 'Contact',
    href: contact(),
    icon: Mail,
    active: isCurrentUrl(contact()),
}));

function navLinkClass(active: boolean): string {
    if (isOverlay.value) {
        return active
            ? 'bg-white/15 text-white'
            : 'text-white/80 hover:bg-white/10 hover:text-white';
    }

    return active
        ? 'bg-muted text-foreground'
        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground';
}

function handleLogout(): void {
    mobileMenuOpen.value = false;
    router.flushAll();
}
</script>

<template>
    <header
        :class="
            isOverlay
                ? 'relative z-20'
                : 'sticky top-0 z-40 border-b border-border/70 bg-background/85 backdrop-blur-md supports-[backdrop-filter]:bg-background/70'
        "
    >
        <div
            :class="
                cn(
                    'mx-auto flex items-center justify-between gap-4 px-4 sm:px-6 lg:px-8',
                    isOverlay ? 'h-20' : 'h-16',
                    containerClass,
                )
            "
        >
            <Link :href="home()" class="shrink-0" aria-label="TripPilot home">
                <TripPilotBrand
                    :variant="isOverlay ? 'light' : 'default'"
                    :size="isOverlay ? 'md' : 'sm'"
                    show-tagline
                />
            </Link>

            <!-- Desktop -->
            <nav class="hidden items-center gap-1 md:flex">
                <Link
                    v-for="item in navItems"
                    :key="item.title"
                    :href="item.href"
                    class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors"
                    :class="navLinkClass(item.active)"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.title }}
                </Link>

                <span
                    class="mx-2 h-5 w-px"
                    :class="isOverlay ? 'bg-white/25' : 'bg-border'"
                />

                <template v-if="user">
                    <Button
                        as-child
                        size="sm"
                        :variant="isOverlay ? 'ghost' : 'default'"
                        :class="
                            isOverlay
                                ? 'bg-white text-teal-800 shadow-lg hover:bg-white/90'
                                : ''
                        "
                    >
                        <Link :href="dashboard()">
                            <LayoutGrid class="size-4" />
                            Dashboard
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        :class="navLinkClass(false)"
                    >
                        <Link
                            :href="logout()"
                            as="button"
                            data-test="public-logout-button"
                            @click="handleLogout"
                        >
                            <LogOut class="size-4" />
                            Log out
                        </Link>
                    </Button>
                </template>
                <template v-else>
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        :class="navLinkClass(false)"
                    >
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button
                        as-child
                        size="sm"
                        :variant="isOverlay ? 'ghost' : 'default'"
                        :class="
                            isOverlay
                                ? 'bg-white text-teal-800 shadow-lg hover:bg-white/90'
                                : ''
                        "
                    >
                        <Link :href="register()">Get started free</Link>
                    </Button>
                </template>

                <Link
                    :href="contactItem.href"
                    class="ml-1 inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors"
                    :class="navLinkClass(contactItem.active)"
                    :aria-current="contactItem.active ? 'page' : undefined"
                >
                    <component :is="contactItem.icon" class="size-4" />
                    {{ contactItem.title }}
                </Link>
            </nav>

            <!-- Mobile -->
            <Sheet v-model:open="mobileMenuOpen">
                <SheetTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="md:hidden"
                        :class="
                            isOverlay
                                ? 'text-white hover:bg-white/10 hover:text-white'
                                : ''
                        "
                        aria-label="Open menu"
                    >
                        <Menu class="size-5" />
                    </Button>
                </SheetTrigger>
                <SheetContent side="right" class="flex w-[300px] flex-col p-0">
                    <SheetHeader class="border-b px-5 py-4 text-left">
                        <SheetTitle class="sr-only">Navigation menu</SheetTitle>
                        <TripPilotBrand size="sm" show-tagline />
                    </SheetHeader>

                    <nav class="flex flex-1 flex-col gap-1 p-3">
                        <Link
                            v-for="item in navItems"
                            :key="item.title"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                            :class="
                                item.active
                                    ? 'bg-muted text-foreground'
                                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                            "
                            @click="mobileMenuOpen = false"
                        >
                            <component :is="item.icon" class="size-4" />
                            {{ item.title }}
                        </Link>
                        <Link
                            v-if="user"
                            :href="dashboard()"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground"
                            @click="mobileMenuOpen = false"
                        >
                            <LayoutGrid class="size-4" />
                            Dashboard
                        </Link>
                        <Link
                            :href="contactItem.href"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                            :class="
                                contactItem.active
                                    ? 'bg-muted text-foreground'
                                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                            "
                            @click="mobileMenuOpen = false"
                        >
                            <component :is="contactItem.icon" class="size-4" />
                            {{ contactItem.title }}
                        </Link>
                    </nav>

                    <div class="flex flex-col gap-2 border-t p-4">
                        <template v-if="user">
                            <p
                                class="truncate px-1 text-xs text-muted-foreground"
                            >
                                Signed in as
                                <span class="font-medium text-foreground">{{
                                    user.first_name
                                }}</span>
                            </p>
                            <Button variant="outline" as-child class="w-full">
                                <Link
                                    :href="logout()"
                                    as="button"
                                    @click="handleLogout"
                                >
                                    <LogOut class="size-4" />
                                    Log out
                                </Link>
                            </Button>
                        </template>
                        <template v-else>
                            <Button as-child class="w-full">
                                <Link
                                    :href="register()"
                                    @click="mobileMenuOpen = false"
                                >
                                    <UserPlus class="size-4" />
                                    Get started free
                                </Link>
                            </Button>
                            <Button variant="outline" as-child class="w-full">
                                <Link
                                    :href="login()"
                                    @click="mobileMenuOpen = false"
                                >
                                    <LogIn class="size-4" />
                                    Log in
                                </Link>
                            </Button>
                        </template>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    </header>
</template>
