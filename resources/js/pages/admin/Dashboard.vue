<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bot, Map, Shield, Users } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminTripsIndex } from '@/routes/admin/trips';
import { index as usersIndex } from '@/routes/admin/users';
import { settings as superSettings } from '@/routes/admin/super';

const props = defineProps<{
    stats: {
        users: {
            total: number;
            admins: number;
        };
        trips: {
            total: number;
            vacation: number;
            road: number;
        };
        ai_requests: {
            total: number;
            chat_replies: number;
            itineraries: number;
        };
    };
}>();

const formatCount = (value: number): string => value.toLocaleString();

const userHint = computed(() => {
    const { admins } = props.stats.users;

    if (admins === 0) {
        return 'Registered accounts';
    }

    return `${formatCount(admins)} admin${admins === 1 ? '' : 's'}`;
});

const tripHint = computed(() => {
    const { vacation, road } = props.stats.trips;

    return `${formatCount(vacation)} vacation · ${formatCount(road)} road`;
});

const aiHint = computed(() => {
    const { chat_replies, itineraries } = props.stats.ai_requests;

    return `${formatCount(chat_replies)} chat · ${formatCount(itineraries)} itineraries`;
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: adminDashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Admin Dashboard"
            description="Platform overview and management tools."
            :icon="Shield"
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link :href="usersIndex()" class="block transition-opacity hover:opacity-90">
                <StatCard
                    label="Users"
                    :value="formatCount(stats.users.total)"
                    :hint="userHint"
                    :icon="Users"
                    accent="primary"
                />
            </Link>
            <Link
                :href="adminTripsIndex()"
                class="block transition-opacity hover:opacity-90"
            >
                <StatCard
                    label="Trips"
                    :value="formatCount(stats.trips.total)"
                    :hint="tripHint"
                    :icon="Map"
                    accent="sky"
                />
            </Link>
            <StatCard
                label="AI Requests"
                :value="formatCount(stats.ai_requests.total)"
                :hint="aiHint"
                :icon="Bot"
                accent="violet"
            />
        </div>

        <Card class="border-sidebar-border/70 dark:border-sidebar-border">
            <CardHeader>
                <CardTitle class="text-base">Platform status</CardTitle>
                <CardDescription>
                    Phase 6 admin tools are live. Super admins can manage
                    integration drivers and API keys from settings.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        class="rounded-lg border border-border/60 bg-muted/30 p-4"
                    >
                        <p class="text-sm font-medium">Foundation</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Roles, middleware, and admin routes.
                        </p>
                        <span
                            class="mt-3 inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                        >
                            Complete
                        </span>
                    </div>
                    <div
                        class="rounded-lg border border-border/60 bg-muted/30 p-4"
                    >
                        <p class="text-sm font-medium">Dashboard stats</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Live counts for users, trips, and AI usage.
                        </p>
                        <span
                            class="mt-3 inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                        >
                            Complete
                        </span>
                    </div>
                    <div
                        class="rounded-lg border border-border/60 bg-muted/30 p-4"
                    >
                        <p class="text-sm font-medium">User & trip tools</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Role management and cross-account trip moderation.
                        </p>
                        <span
                            class="mt-3 inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                        >
                            Complete
                        </span>
                    </div>
                    <div
                        class="rounded-lg border border-border/60 bg-muted/30 p-4"
                    >
                        <p class="text-sm font-medium">Integrations</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Driver switches, toggles, and encrypted API keys.
                        </p>
                        <Link
                            :href="superSettings()"
                            class="mt-3 inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary hover:underline"
                        >
                            Super admin settings
                        </Link>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
