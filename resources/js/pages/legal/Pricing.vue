<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BadgeCheck, Coins, IndianRupee, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import LegalPage from '@/components/legal/LegalPage.vue';
import { Button } from '@/components/ui/button';
import { formatCredits } from '@/composables/useCredits';
import { register, refundPolicy, terms } from '@/routes';
import { buy as buyCredits } from '@/routes/credits';
import type { CreditServiceCost } from '@/types/credits';
import type { LegalDetails } from '@/types/legal';

const props = defineProps<{
    legal: LegalDetails;
    signup_bonus: number;
    packs: {
        id: number;
        name: string;
        price_paise: number;
        credits: number;
        is_featured: boolean;
    }[];
    costs: CreditServiceCost[];
}>();

const page = usePage();
const isSignedIn = computed(() => Boolean(page.props.auth?.user));

const freeFeatures = [
    'Creating trips, road trips and group tours',
    'Routes, maps, nearby amenities and hotels',
    'Weather forecasts',
    'Trip cover photos (automatic or uploaded)',
    'Expense tracking and sharing trips with members',
];

function formatRupees(paise: number): string {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: paise % 100 === 0 ? 0 : 2,
    }).format(paise / 100);
}

const highestPricePerCredit = computed(() =>
    Math.max(...props.packs.map((pack) => pack.price_paise / pack.credits)),
);

function savingsPercent(pack: (typeof props.packs)[number]): number {
    return Math.floor(
        (1 - pack.price_paise / pack.credits / highestPricePerCredit.value) *
            100,
    );
}
</script>

<template>
    <LegalPage
        title="Pricing"
        description="TripPilot is free to use. AI features use credits, bought in one-time packs."
        :icon="IndianRupee"
        :legal="legal"
    >
        <div class="space-y-10">
            <section class="space-y-4">
                <h2 class="text-lg font-semibold">Credit packs</h2>
                <p
                    v-if="packs.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Credit packs are not on sale right now.
                </p>
                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="pack in packs"
                        :key="pack.id"
                        :class="[
                            'flex items-center justify-between gap-4 rounded-2xl border p-4 shadow-sm',
                            pack.is_featured
                                ? 'border-teal-500/60 bg-teal-50/60 dark:bg-teal-500/10'
                                : 'border-border/60 bg-card',
                        ]"
                        data-test="pricing-pack"
                    >
                        <div>
                            <p
                                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                            >
                                {{ pack.name }}
                                <span
                                    v-if="pack.is_featured"
                                    class="inline-flex items-center gap-1 rounded-full bg-teal-600 px-2 py-0.5 text-[11px] font-semibold text-white"
                                >
                                    <Sparkles class="size-3" />
                                    Most popular
                                </span>
                            </p>
                            <p
                                class="flex items-center gap-1.5 text-xl font-bold"
                            >
                                <Coins class="size-5 text-teal-600" />
                                {{ pack.credits }} credits
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-semibold">
                                {{ formatRupees(pack.price_paise) }}
                            </p>
                            <p
                                v-if="savingsPercent(pack) > 0"
                                class="text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                Save {{ savingsPercent(pack) }}%
                            </p>
                        </div>
                    </div>
                </div>
                <ul class="space-y-1.5 text-sm text-muted-foreground">
                    <li class="flex items-center gap-2">
                        <BadgeCheck class="size-4 text-emerald-600" />
                        One-time payments in INR. No subscriptions or automatic
                        renewals.
                    </li>
                    <li class="flex items-center gap-2">
                        <BadgeCheck class="size-4 text-emerald-600" />
                        Credits never expire.
                    </li>
                    <li v-if="signup_bonus > 0" class="flex items-center gap-2">
                        <BadgeCheck class="size-4 text-emerald-600" />
                        New accounts get {{ signup_bonus }} free credits after
                        verifying their email.
                    </li>
                    <li class="flex items-center gap-2">
                        <BadgeCheck class="size-4 text-emerald-600" />
                        Secure payments by Razorpay: UPI, cards, netbanking and
                        wallets.
                    </li>
                </ul>
                <Button as-child>
                    <Link :href="isSignedIn ? buyCredits() : register()">
                        {{
                            isSignedIn
                                ? 'Buy credits'
                                : 'Sign up to get free credits'
                        }}
                    </Link>
                </Button>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-semibold">What uses credits</h2>
                <ul
                    class="divide-y divide-border/60 rounded-2xl border border-border/60 bg-card"
                >
                    <li
                        v-for="cost in costs"
                        :key="cost.service"
                        class="flex items-center justify-between gap-4 px-4 py-3 text-sm"
                    >
                        <span>{{ cost.label }}</span>
                        <span class="font-semibold tabular-nums">
                            {{
                                cost.credits === 0
                                    ? 'Free'
                                    : formatCredits(cost.credits)
                            }}
                        </span>
                    </li>
                </ul>
                <p class="text-sm text-muted-foreground">
                    If an AI request fails, its credits are returned
                    automatically.
                </p>
            </section>

            <section class="space-y-3">
                <h2 class="text-lg font-semibold">Always free</h2>
                <ul
                    class="grid gap-2 text-sm text-muted-foreground sm:grid-cols-2"
                >
                    <li
                        v-for="feature in freeFeatures"
                        :key="feature"
                        class="flex items-center gap-2"
                    >
                        <BadgeCheck class="size-4 shrink-0 text-emerald-600" />
                        {{ feature }}
                    </li>
                </ul>
            </section>

            <p class="text-sm text-muted-foreground">
                Buying credits means you accept our
                <Link :href="terms()" class="font-medium underline">
                    Terms & Conditions
                </Link>
                and
                <Link :href="refundPolicy()" class="font-medium underline">
                    Refund & Cancellation Policy
                </Link>
                . Credit purchases are final; only failed, duplicate or wrongly
                charged payments are refunded.
            </p>
        </div>
    </LegalPage>
</template>
