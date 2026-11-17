<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Receipt } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { settings as superSettings } from '@/routes/admin/super';
import { index as creditPurchasesIndex } from '@/routes/admin/super/credit-purchases';
import { show as creditsShow } from '@/routes/admin/super/credits';
import type { Paginated } from '@/types/admin';

type CreditPurchase = {
    id: number;
    user_name: string | null;
    user_email: string | null;
    pack_name: string;
    amount: number;
    amount_refunded: number;
    credits: number;
    credits_reversed: number;
    status: 'paid' | 'refunded';
    status_label: string;
    razorpay_payment_id: string | null;
    paid_at: string | null;
};

defineProps<{
    orders: Paginated<CreditPurchase>;
}>();

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatRupees(amount: number): string {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(amount);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Super Admin', href: superSettings() },
            { title: 'Credit Purchases', href: creditPurchasesIndex() },
        ],
    },
});
</script>

<template>
    <Head title="Credit Purchases" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Credit Purchases"
            description="Paid and refunded credit pack orders. Refund a payment from the Razorpay dashboard; the user's unspent credits are taken back automatically."
            :icon="Receipt"
        />

        <Card>
            <CardContent class="p-0">
                <div
                    v-if="orders.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No purchases yet.
                </div>

                <ul v-else class="divide-y divide-border/60">
                    <li
                        v-for="order in orders.data"
                        :key="order.id"
                        class="flex flex-col gap-3 p-4 text-sm sm:flex-row sm:items-center sm:justify-between"
                        data-test="credit-purchase"
                    >
                        <div class="min-w-0 space-y-0.5">
                            <p class="font-medium">
                                {{ order.user_name ?? 'Deleted user' }}
                                <span
                                    v-if="order.user_email"
                                    class="font-normal text-muted-foreground"
                                >
                                    · {{ order.user_email }}
                                </span>
                            </p>
                            <p class="text-muted-foreground">
                                {{ order.pack_name }} ·
                                {{ order.credits }} credits
                                <span v-if="order.credits_reversed > 0">
                                    · {{ order.credits_reversed }} taken back
                                </span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ order.razorpay_payment_id ?? '—' }} ·
                                {{ formatDate(order.paid_at) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <div class="text-right">
                                <p class="font-semibold tabular-nums">
                                    {{ formatRupees(order.amount) }}
                                </p>
                                <p
                                    v-if="order.amount_refunded > 0"
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ formatRupees(order.amount_refunded) }}
                                    refunded
                                </p>
                            </div>
                            <Badge
                                :variant="
                                    order.status === 'paid'
                                        ? 'default'
                                        : 'outline'
                                "
                            >
                                {{ order.status_label }}
                            </Badge>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <div
            v-if="orders.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ orders.from ?? 0 }}–{{ orders.to ?? 0 }} of
                {{ orders.total }}
            </p>

            <div class="flex flex-wrap gap-2">
                <template
                    v-for="link in orders.links"
                    :key="`${link.label}-${link.url}`"
                >
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                    >
                        <Link :href="link.url" preserve-scroll>
                            <span v-html="link.label" />
                        </Link>
                    </Button>
                    <Button v-else size="sm" variant="outline" disabled>
                        <span v-html="link.label" />
                    </Button>
                </template>
            </div>
        </div>

        <Button variant="outline" as-child class="self-start">
            <Link :href="creditsShow()">
                <ArrowLeft class="mr-2 size-4" />
                Back to Credits Settings
            </Link>
        </Button>
    </div>
</template>
