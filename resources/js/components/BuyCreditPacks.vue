<script setup lang="ts">
import { Link, router, useHttp } from '@inertiajs/vue3';
import { Coins, ShieldCheck, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CreditOrderController from '@/actions/App/Http/Controllers/Credits/CreditOrderController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useCredits } from '@/composables/useCredits';
import { refundPolicy, terms } from '@/routes';
import type { CreditPackOption } from '@/types/credits';

const props = defineProps<{
    packs: CreditPackOption[];
}>();

/** The highlighted pack gets its own section; the rest share a grid. */
const featuredPack = computed(
    () => props.packs.find((pack) => pack.is_featured) ?? null,
);

const otherPacks = computed(() =>
    props.packs.filter((pack) => pack.id !== featuredPack.value?.id),
);

const { costOf } = useCredits();

const itineraryCost = computed(() => costOf('itinerary_generation'));

function pricePerCredit(pack: CreditPackOption): number {
    return pack.price_paise / 100 / pack.credits;
}

/**
 * Percentage saved per credit compared with the most expensive pack, or 0
 * when the pack is that pack.
 */
function savingsPercent(pack: CreditPackOption): number {
    const highest = Math.max(...props.packs.map(pricePerCredit));

    return Math.floor((1 - pricePerCredit(pack) / highest) * 100);
}

type RazorpaySuccess = {
    razorpay_payment_id: string;
    razorpay_order_id: string;
    razorpay_signature: string;
};

type RazorpayInstance = {
    open: () => void;
    on: (
        event: 'payment.failed',
        handler: (response: { error: { description?: string } }) => void,
    ) => void;
};

type CheckoutResponse = {
    order_id: number;
    checkout: {
        key: string;
        order_id: string;
        amount: number;
        currency: string;
        name: string;
        description: string;
        prefill: { name: string; email: string };
    };
};

declare global {
    interface Window {
        Razorpay?: new (options: Record<string, unknown>) => RazorpayInstance;
    }
}

const CHECKOUT_SCRIPT = 'https://checkout.razorpay.com/v1/checkout.js';

let checkoutScript: Promise<void> | null = null;

function loadCheckoutScript(): Promise<void> {
    if (window.Razorpay) {
        return Promise.resolve();
    }

    checkoutScript ??= new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = CHECKOUT_SCRIPT;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            checkoutScript = null;
            script.remove();
            reject(new Error('Razorpay Checkout failed to load.'));
        };
        document.head.appendChild(script);
    });

    return checkoutScript;
}

const http = useHttp<{ credit_pack_id: number | null }, CheckoutResponse>({
    credit_pack_id: null,
});

const buyingPackId = ref<number | null>(null);

function formatRupees(amount: number): string {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(amount);
}

function confirmPayment(orderId: number, payment: RazorpaySuccess): void {
    router.post(
        CreditOrderController.verify.url(orderId),
        {
            razorpay_payment_id: payment.razorpay_payment_id,
            razorpay_signature: payment.razorpay_signature,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                buyingPackId.value = null;
            },
        },
    );
}

async function buy(pack: CreditPackOption): Promise<void> {
    if (buyingPackId.value !== null) {
        return;
    }

    buyingPackId.value = pack.id;
    http.credit_pack_id = pack.id;

    try {
        const [response] = await Promise.all([
            http.post(CreditOrderController.store.url()),
            loadCheckoutScript(),
        ]);

        if (!response || !window.Razorpay) {
            buyingPackId.value = null;

            return;
        }

        const checkout = new window.Razorpay({
            ...response.checkout,
            theme: { color: '#0d9488' },
            handler: (payment: RazorpaySuccess) =>
                confirmPayment(response.order_id, payment),
            modal: {
                ondismiss: () => {
                    buyingPackId.value = null;
                },
            },
        });

        checkout.on('payment.failed', (failure) => {
            toast.error(
                failure.error.description ??
                    'The payment failed. No money was taken.',
            );
        });

        checkout.open();
    } catch {
        buyingPackId.value = null;
        toast.error('We could not start the payment. Please try again.');
    }
}
</script>

<template>
    <div class="space-y-6">
        <section
            v-if="featuredPack"
            class="rounded-2xl bg-linear-to-r from-teal-400 via-emerald-400 to-cyan-400 p-0.5 shadow-lg shadow-teal-500/15"
            data-test="featured-credit-pack"
        >
            <div
                class="flex flex-col gap-4 rounded-[calc(1rem-2px)] bg-linear-to-r from-teal-50 via-white to-cyan-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5 dark:from-teal-950 dark:via-background dark:to-cyan-950"
            >
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-linear-to-r from-teal-600 to-emerald-600 px-2 py-0.5 text-[11px] font-semibold text-white"
                        >
                            <Sparkles class="size-3" />
                            Most popular
                        </span>
                        <span
                            class="text-sm font-semibold text-teal-700 dark:text-teal-300"
                        >
                            {{ featuredPack.name }}
                        </span>
                    </div>
                    <p
                        class="flex items-center gap-1.5 text-2xl font-bold tracking-tight"
                    >
                        <Coins class="size-5 text-teal-600" />
                        {{ featuredPack.credits }}
                        <span class="text-sm font-normal text-muted-foreground">
                            credits
                        </span>
                    </p>
                    <p
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span>
                            {{ formatRupees(pricePerCredit(featuredPack)) }} /
                            credit
                        </span>
                        <span
                            v-if="savingsPercent(featuredPack) > 0"
                            class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                        >
                            Save {{ savingsPercent(featuredPack) }}%
                        </span>
                        <span v-if="itineraryCost">
                            · about
                            {{
                                Math.floor(featuredPack.credits / itineraryCost)
                            }}
                            itinerary plans
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-4 sm:shrink-0">
                    <p class="text-2xl font-bold tracking-tight">
                        {{ formatRupees(featuredPack.price) }}
                    </p>
                    <Button
                        type="button"
                        class="flex-1 sm:flex-none"
                        :disabled="buyingPackId !== null"
                        @click="buy(featuredPack)"
                    >
                        <Spinner
                            v-if="buyingPackId === featuredPack.id"
                            class="size-4"
                        />
                        Buy for {{ formatRupees(featuredPack.price) }}
                    </Button>
                </div>
            </div>
        </section>

        <div v-if="otherPacks.length > 0" class="space-y-3">
            <h2
                v-if="featuredPack"
                class="text-sm font-semibold text-muted-foreground"
            >
                Other packs
            </h2>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="pack in otherPacks"
                    :key="pack.id"
                    class="flex flex-col gap-4 rounded-2xl border border-border/60 bg-card p-4 shadow-sm transition-shadow hover:shadow-md"
                    data-test="credit-pack"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                {{ pack.name }}
                            </p>
                            <p
                                class="flex items-center gap-1.5 text-2xl font-bold tracking-tight"
                            >
                                <Coins class="size-5 text-teal-600" />
                                {{ pack.credits }}
                                <span
                                    class="text-sm font-normal text-muted-foreground"
                                >
                                    credits
                                </span>
                            </p>
                        </div>
                        <span
                            v-if="savingsPercent(pack) > 0"
                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                        >
                            Save {{ savingsPercent(pack) }}%
                        </span>
                    </div>

                    <div class="mt-auto space-y-3">
                        <div class="flex items-baseline justify-between">
                            <p class="text-lg font-semibold">
                                {{ formatRupees(pack.price) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatRupees(pricePerCredit(pack)) }} /
                                credit
                            </p>
                        </div>
                        <Button
                            type="button"
                            class="w-full"
                            :disabled="buyingPackId !== null"
                            @click="buy(pack)"
                        >
                            <Spinner
                                v-if="buyingPackId === pack.id"
                                class="size-4"
                            />
                            Buy for {{ formatRupees(pack.price) }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <InputError :message="http.errors.credit_pack_id" />

        <p class="flex items-start gap-2 text-xs text-muted-foreground">
            <ShieldCheck class="mt-0.5 size-4 shrink-0" />
            Payments are processed securely by Razorpay (UPI, cards, netbanking
            and wallets). Credits are added as soon as the payment succeeds. By
            buying, you accept our
            <Link :href="terms()" class="underline">Terms & Conditions</Link>
            and
            <Link :href="refundPolicy()" class="underline">
                Refund & Cancellation Policy
            </Link>
            : purchases are final, and only failed, duplicate or wrongly charged
            payments are refunded.
        </p>
    </div>
</template>
