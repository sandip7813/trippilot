<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import CreditBalanceSummary from '@/components/CreditBalanceSummary.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { history as creditHistory } from '@/routes/credits';
import type { Paginated } from '@/types/admin';
import type { CreditTransaction } from '@/types/credits';

defineProps<{
    balance: number;
    charging_enabled: boolean;
    is_exempt: boolean;
    transactions: Paginated<CreditTransaction>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Credit history', href: creditHistory() }],
    },
});

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
</script>

<template>
    <Head title="Credit history" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Credit history"
            description="Credits added to and used from your balance."
            :icon="History"
        />

        <CreditBalanceSummary
            :balance="balance"
            :charging-enabled="charging_enabled"
            :is-exempt="is_exempt"
        />

        <Card>
            <CardContent class="p-0">
                <p
                    v-if="transactions.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No credit activity yet.
                </p>

                <ul v-else class="divide-y divide-border/60">
                    <li
                        v-for="transaction in transactions.data"
                        :key="transaction.id"
                        class="flex items-start justify-between gap-4 px-4 py-3 text-sm sm:px-6"
                    >
                        <div class="min-w-0 space-y-0.5">
                            <p class="font-medium">
                                {{ transaction.label }}
                                <span
                                    v-if="transaction.type === 'refund'"
                                    class="font-normal text-muted-foreground"
                                >
                                    (refunded)
                                </span>
                            </p>
                            <p
                                v-if="
                                    transaction.trip_title || transaction.note
                                "
                                class="truncate text-muted-foreground"
                            >
                                {{ transaction.trip_title ?? transaction.note }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDate(transaction.created_at) }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p
                                :class="[
                                    'font-semibold tabular-nums',
                                    transaction.amount > 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-foreground',
                                ]"
                            >
                                {{ transaction.amount > 0 ? '+' : ''
                                }}{{ transaction.amount }}
                            </p>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                Balance {{ transaction.balance_after }}
                            </p>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <div
            v-if="transactions.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ transactions.from ?? 0 }}–{{
                    transactions.to ?? 0
                }}
                of {{ transactions.total }}
            </p>

            <div class="flex flex-wrap gap-2">
                <template
                    v-for="link in transactions.links"
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
    </div>
</template>
