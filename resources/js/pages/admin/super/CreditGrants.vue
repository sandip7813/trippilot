<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, HandCoins } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { settings as superSettings } from '@/routes/admin/super';
import { index as creditGrantsIndex } from '@/routes/admin/super/credit-grants';
import { index as usersIndex } from '@/routes/admin/users';
import type { Paginated } from '@/types/admin';

type CreditGrant = {
    id: number;
    credits: number;
    balance_after: number;
    note: string | null;
    user_name: string | null;
    user_email: string | null;
    granted_by_name: string | null;
    created_at: string | null;
};

defineProps<{
    grants: Paginated<CreditGrant>;
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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Super Admin', href: superSettings() },
            { title: 'Credits Added', href: creditGrantsIndex() },
        ],
    },
});
</script>

<template>
    <Head title="Credits Added" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Credits Added"
            description="Every time a super admin added credits to a user. Users see these entries in their Credit history."
            :icon="HandCoins"
        />

        <Card>
            <CardContent class="p-0">
                <div
                    v-if="grants.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No credits have been added yet. Add credits from the
                    <Link :href="usersIndex()" class="font-medium underline">
                        Users
                    </Link>
                    page.
                </div>

                <ul v-else class="divide-y divide-border/60">
                    <li
                        v-for="grant in grants.data"
                        :key="grant.id"
                        class="flex flex-col gap-3 p-4 text-sm sm:flex-row sm:items-center sm:justify-between"
                        data-test="credit-grant"
                    >
                        <div class="min-w-0 space-y-0.5">
                            <p class="font-medium">
                                {{ grant.user_name ?? 'Deleted user' }}
                                <span
                                    v-if="grant.user_email"
                                    class="font-normal text-muted-foreground"
                                >
                                    · {{ grant.user_email }}
                                </span>
                            </p>
                            <p v-if="grant.note" class="text-muted-foreground">
                                “{{ grant.note }}”
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Added by
                                {{ grant.granted_by_name ?? 'a deleted admin' }}
                                · {{ formatDate(grant.created_at) }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p
                                class="font-semibold text-emerald-600 tabular-nums dark:text-emerald-400"
                            >
                                +{{ grant.credits }} credits
                            </p>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                Balance after {{ grant.balance_after }}
                            </p>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <div
            v-if="grants.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ grants.from ?? 0 }}–{{ grants.to ?? 0 }} of
                {{ grants.total }}
            </p>

            <div class="flex flex-wrap gap-2">
                <template
                    v-for="link in grants.links"
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
            <Link :href="usersIndex()">
                <ArrowLeft class="mr-2 size-4" />
                Back to users
            </Link>
        </Button>
    </div>
</template>
