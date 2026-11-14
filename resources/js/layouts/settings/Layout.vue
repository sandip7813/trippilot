<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CalendarHeart,
    Camera,
    Mail,
    Palette,
    ShieldCheck,
    Sparkles,
    UserRound,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editProfilePicture } from '@/routes/profile-picture';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

type SettingsNavItem = NavItem & {
    icon: Component;
    caption: string;
    activeClass: string;
    iconClass: string;
};

const sidebarNavItems: SettingsNavItem[] = [
    {
        title: 'Profile',
        caption: 'Name, contact & home city',
        href: editProfile(),
        icon: UserRound,
        activeClass:
            'bg-linear-to-r from-teal-500 to-emerald-500 shadow-teal-500/30',
        iconClass:
            'bg-teal-100 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300',
    },
    {
        title: 'Profile picture',
        caption: 'Your photo across TripPilot',
        href: editProfilePicture(),
        icon: Camera,
        activeClass:
            'bg-linear-to-r from-cyan-500 to-sky-500 shadow-cyan-500/30',
        iconClass:
            'bg-cyan-100 text-cyan-600 dark:bg-cyan-500/15 dark:text-cyan-300',
    },
    {
        title: 'Security',
        caption: 'Password & protection',
        href: editSecurity(),
        icon: ShieldCheck,
        activeClass:
            'bg-linear-to-r from-violet-500 to-fuchsia-500 shadow-violet-500/30',
        iconClass:
            'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
    },
    {
        title: 'Appearance',
        caption: 'Theme & display',
        href: editAppearance(),
        icon: Palette,
        activeClass:
            'bg-linear-to-r from-amber-500 to-orange-500 shadow-amber-500/30',
        iconClass:
            'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();

const page = usePage();
const user = computed(() => page.props.auth.user);

const roleLabels: Record<string, string> = {
    user: 'Traveller',
    admin: 'Admin',
    super_admin: 'Super Admin',
};

const roleLabel = computed(() => roleLabels[user.value.role] ?? 'Traveller');

const memberSince = computed(() =>
    new Date(user.value.created_at).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    }),
);
</script>

<template>
    <div class="flex flex-col gap-6 px-4 py-6">
        <div
            class="brand-gradient relative isolate overflow-hidden rounded-3xl p-6 text-white shadow-xl shadow-cyan-500/20 sm:p-8"
        >
            <div
                class="pointer-events-none absolute -top-16 -right-10 -z-10 size-56 rounded-full bg-white/15 blur-2xl"
            />
            <div
                class="pointer-events-none absolute -bottom-20 left-1/3 -z-10 size-64 rounded-full bg-fuchsia-400/30 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-6 right-1/4 -z-10 size-24 rounded-full bg-amber-300/30 blur-2xl"
            />

            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-center sm:gap-6"
            >
                <div class="relative shrink-0">
                    <UserAvatar
                        :src="user.avatar"
                        :name="user.name"
                        class="size-20 rounded-2xl ring-4 ring-white/40 sm:size-24"
                        fallback-class="ring-0"
                    />
                    <span
                        v-if="user.email_verified_at"
                        class="absolute -right-1.5 -bottom-1.5 flex size-7 items-center justify-center rounded-full bg-emerald-400 text-white shadow-md ring-2 ring-white"
                        title="Email verified"
                    >
                        <BadgeCheck class="size-4" />
                    </span>
                </div>

                <div class="min-w-0 flex-1 space-y-2">
                    <p
                        class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-white/80 uppercase"
                    >
                        <Sparkles class="size-3.5" />
                        Account settings
                    </p>
                    <h1
                        class="truncate text-2xl font-bold tracking-tight sm:text-3xl"
                    >
                        {{ user.name }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 backdrop-blur-sm"
                        >
                            <Mail class="size-3.5" />
                            <span class="truncate">{{ user.email }}</span>
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 backdrop-blur-sm"
                        >
                            <CalendarHeart class="size-3.5" />
                            Member since {{ memberSince }}
                        </span>
                        <span
                            class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-violet-700 shadow-sm"
                        >
                            {{ roleLabel }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            <aside class="w-full lg:w-64 lg:shrink-0">
                <nav
                    class="flex gap-2 overflow-x-auto pb-1 lg:sticky lg:top-6 lg:flex-col lg:overflow-visible lg:pb-0"
                    aria-label="Settings"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        :class="[
                            'group flex min-w-max items-center gap-3 rounded-xl px-3 py-2.5 transition-all duration-200 lg:min-w-0',
                            isCurrentOrParentUrl(item.href)
                                ? ['text-white shadow-lg', item.activeClass]
                                : 'border border-border/60 bg-card hover:-translate-y-0.5 hover:shadow-md',
                        ]"
                    >
                        <span
                            :class="[
                                'flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-white/20 text-white'
                                    : item.iconClass,
                            ]"
                        >
                            <component :is="item.icon" class="size-4.5" />
                        </span>
                        <span class="flex flex-col leading-tight">
                            <span class="text-sm font-semibold">
                                {{ item.title }}
                            </span>
                            <span
                                :class="[
                                    'hidden text-xs lg:block',
                                    isCurrentOrParentUrl(item.href)
                                        ? 'text-white/80'
                                        : 'text-muted-foreground',
                                ]"
                            >
                                {{ item.caption }}
                            </span>
                        </span>
                    </Link>
                </nav>
            </aside>

            <div class="min-w-0 flex-1 lg:max-w-3xl">
                <section class="flex flex-col gap-6">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
