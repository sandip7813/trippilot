<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LockKeyhole, Plane, ShieldCheck, Sparkles, Users } from '@lucide/vue';
import { computed } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';

defineProps<{
    title?: string;
    description?: string;
}>();

type Pass = {
    from: { code: string; label: string };
    to: { code: string; label: string };
    details: [string, string][];
};

/**
 * What the boarding-pass stub shows for each auth screen.
 */
const passes: Record<string, Pass> = {
    'auth/Login': {
        from: { code: 'YOU', label: 'Traveler' },
        to: { code: 'DSH', label: 'Your dashboard' },
        details: [
            ['Gate', 'A1'],
            ['Boarding', 'Now'],
            ['Seat', 'Window'],
            ['Class', 'Explorer'],
        ],
    },
    'auth/Register': {
        from: { code: 'NEW', label: 'New traveler' },
        to: { code: 'TRP', label: 'Your first trip' },
        details: [
            ['Fare', 'Free'],
            ['Boarding', 'Instant'],
            ['Seat', 'Reserved'],
            ['Class', 'Explorer'],
        ],
    },
    'auth/ForgotPassword': {
        from: { code: 'PWD', label: 'Forgotten' },
        to: { code: 'NEW', label: 'Fresh start' },
        details: [
            ['Status', 'Rebooking'],
            ['Via', 'Email'],
            ['ETA', '1 min'],
            ['Seat', 'Held'],
        ],
    },
    'auth/ResetPassword': {
        from: { code: 'PWD', label: 'Old password' },
        to: { code: 'NEW', label: 'New password' },
        details: [
            ['Status', 'Rebooking'],
            ['Gate', 'Final'],
            ['Seat', 'Held'],
            ['Class', 'Explorer'],
        ],
    },
    'auth/ChangePassword': {
        from: { code: 'OTP', label: 'One-time' },
        to: { code: 'YOU', label: 'Your password' },
        details: [
            ['Status', 'Final check'],
            ['Gate', 'Security'],
            ['Seat', 'Confirmed'],
            ['Class', 'Explorer'],
        ],
    },
    'auth/VerifyEmail': {
        from: { code: 'EML', label: 'Your inbox' },
        to: { code: 'OK', label: 'Verified' },
        details: [
            ['Status', 'Awaiting'],
            ['Via', 'Email'],
            ['Seat', 'Held'],
            ['Class', 'Explorer'],
        ],
    },
};

const page = usePage();
const pass = computed(() => passes[page.component] ?? passes['auth/Login']);

const assurances = [
    { icon: Sparkles, text: 'Free to get started' },
    { icon: LockKeyhole, text: 'Secure sign-in' },
    { icon: Users, text: 'Plan with friends' },
    { icon: ShieldCheck, text: 'No credit card needed' },
];
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <section class="relative isolate flex flex-1 flex-col">
            <img
                src="/images/destination-santorini.jpg"
                alt=""
                class="absolute inset-0 -z-20 size-full object-cover"
                fetchpriority="high"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-b from-slate-950/80 via-slate-950/55 to-slate-950/85"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-r from-teal-950/60 via-transparent to-indigo-950/50"
            />

            <!-- Dotted flight path behind the ticket -->
            <svg
                class="pointer-events-none absolute inset-x-0 top-1/2 -z-10 hidden h-72 w-full -translate-y-1/2 text-white/25 lg:block"
                viewBox="0 0 1200 300"
                preserveAspectRatio="none"
                fill="none"
                aria-hidden="true"
            >
                <path
                    d="M-20 260 C 260 40, 520 40, 640 150 S 1000 280, 1220 30"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-dasharray="2 10"
                    stroke-linecap="round"
                />
            </svg>

            <PublicHeader variant="overlay" />

            <div
                class="flex flex-1 flex-col items-center justify-center gap-8 px-4 py-10 sm:px-6 lg:py-16"
            >
                <div
                    class="w-full max-w-4xl drop-shadow-[0_30px_50px_rgb(2_6_23/0.45)]"
                >
                    <div
                        class="flex flex-col overflow-hidden rounded-3xl lg:flex-row lg:ticket-notches"
                        style="--ticket-stub-width: 300px"
                    >
                        <!-- Stub: on top for phones, on the right for desktop -->
                        <aside
                            class="brand-gradient relative order-first flex shrink-0 flex-col gap-5 border-b-2 border-dashed border-white/40 p-6 text-white sm:p-8 lg:order-last lg:w-[300px] lg:justify-between lg:border-b-0 lg:border-l-2"
                        >
                            <div
                                class="flex items-center justify-between text-[11px] font-semibold tracking-[0.2em] text-white/80 uppercase"
                            >
                                <span>Boarding pass</span>
                                <Plane class="size-4" />
                            </div>

                            <div class="flex items-end justify-between gap-3">
                                <div>
                                    <p
                                        class="text-3xl font-extrabold tracking-tight sm:text-4xl"
                                    >
                                        {{ pass.from.code }}
                                    </p>
                                    <p class="text-xs text-white/75">
                                        {{ pass.from.label }}
                                    </p>
                                </div>
                                <div
                                    class="mb-5 flex flex-1 items-center gap-1 text-white/70"
                                >
                                    <span
                                        class="h-px flex-1 border-t border-dashed border-white/60"
                                    />
                                    <Plane class="size-4 rotate-45" />
                                    <span
                                        class="h-px flex-1 border-t border-dashed border-white/60"
                                    />
                                </div>
                                <div class="text-right">
                                    <p
                                        class="text-3xl font-extrabold tracking-tight sm:text-4xl"
                                    >
                                        {{ pass.to.code }}
                                    </p>
                                    <p class="text-xs text-white/75">
                                        {{ pass.to.label }}
                                    </p>
                                </div>
                            </div>

                            <dl
                                class="hidden grid-cols-2 gap-x-4 gap-y-3 rounded-2xl bg-white/10 p-4 backdrop-blur-sm sm:grid"
                            >
                                <div
                                    v-for="[label, value] in pass.details"
                                    :key="label"
                                >
                                    <dt
                                        class="text-[10px] font-semibold tracking-widest text-white/60 uppercase"
                                    >
                                        {{ label }}
                                    </dt>
                                    <dd class="text-sm font-semibold">
                                        {{ value }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="hidden lg:block">
                                <div
                                    class="h-12 w-full ticket-barcode text-white/85"
                                />
                                <p
                                    class="mt-2 text-center font-mono text-[10px] tracking-[0.35em] text-white/60"
                                >
                                    TRIPPILOT · 2026
                                </p>
                            </div>
                        </aside>

                        <!-- Form -->
                        <div
                            class="flex-1 bg-white p-6 text-card-foreground sm:p-10 lg:p-12 dark:bg-slate-900"
                        >
                            <div class="mx-auto max-w-md">
                                <div class="mb-8 space-y-2">
                                    <h1
                                        class="text-2xl font-bold tracking-tight sm:text-3xl"
                                    >
                                        {{ title }}
                                    </h1>
                                    <p
                                        v-if="description"
                                        class="text-sm leading-relaxed text-muted-foreground"
                                    >
                                        {{ description }}
                                    </p>
                                </div>

                                <slot />
                            </div>
                        </div>
                    </div>
                </div>

                <ul
                    class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-white/80"
                >
                    <li
                        v-for="item in assurances"
                        :key="item.text"
                        class="flex items-center gap-2"
                    >
                        <component
                            :is="item.icon"
                            class="size-4 text-teal-300"
                        />
                        {{ item.text }}
                    </li>
                </ul>
            </div>
        </section>

        <PublicFooter />
    </div>
</template>
