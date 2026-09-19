<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bot, Coins, Map, Shield, Users } from '@lucide/vue';
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
import { settings as superSettings } from '@/routes/admin/super';
import { index as adminTripsIndex } from '@/routes/admin/trips';
import { index as usersIndex } from '@/routes/admin/users';

const props = defineProps<{
    analytics: {
        trips: {
            domestic: number;
            international: number;
            by_month: { label: string; count: number }[];
            top_destinations: { label: string; count: number }[];
        };
        ai: {
            total_tokens: number;
            total_cost_usd: number;
            period_days: number;
            period_requests: number;
            period_cost_usd: number;
            by_feature: {
                feature: string;
                label: string;
                requests: number;
                tokens: number;
                cost_usd: number;
            }[];
            daily: { date: string; requests: number; cost_usd: number }[];
            top_users: {
                name: string;
                email: string;
                requests: number;
                cost_usd: number;
            }[];
        };
    };
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

const formatCost = (value: number): string =>
    `$${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`;

const percent = (value: number, max: number): string =>
    `${max > 0 ? Math.max((value / max) * 100, value > 0 ? 3 : 0) : 0}%`;

const maxMonth = computed(() =>
    Math.max(...props.analytics.trips.by_month.map((m) => m.count), 0),
);
const maxDestination = computed(() =>
    Math.max(...props.analytics.trips.top_destinations.map((d) => d.count), 0),
);
const maxFeature = computed(() =>
    Math.max(...props.analytics.ai.by_feature.map((f) => f.requests), 0),
);
const maxDaily = computed(() =>
    Math.max(...props.analytics.ai.daily.map((d) => d.requests), 0),
);

const tripHintScope = computed(() => {
    const { domestic, international } = props.analytics.trips;

    return `${formatCount(domestic)} domestic · ${formatCount(international)} international`;
});

const aiCostHint = computed(
    () =>
        `${formatCost(props.analytics.ai.period_cost_usd)} in last ${props.analytics.ai.period_days} days`,
);

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

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
            <StatCard
                label="Estimated AI cost"
                :value="formatCost(analytics.ai.total_cost_usd)"
                :hint="aiCostHint"
                :icon="Coins"
                accent="amber"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <CardTitle class="text-base">Trips created</CardTitle>
                    <CardDescription>Last 6 months · {{ tripHintScope }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex h-40 items-end gap-3">
                        <div
                            v-for="month in analytics.trips.by_month"
                            :key="month.label"
                            class="flex h-full flex-1 flex-col items-center justify-end gap-1"
                        >
                            <span class="text-xs font-medium">{{ month.count }}</span>
                            <div
                                class="w-full rounded-t bg-sky-500"
                                :style="{ height: percent(month.count, maxMonth) }"
                            />
                            <span class="text-xs text-muted-foreground">{{ month.label }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <CardTitle class="text-base">Top destinations</CardTitle>
                    <CardDescription>Most planned places</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p
                        v-if="analytics.trips.top_destinations.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No destinations yet.
                    </p>
                    <div v-for="item in analytics.trips.top_destinations" :key="item.label">
                        <div class="mb-1 flex justify-between gap-2 text-sm">
                            <span class="truncate">{{ item.label }}</span>
                            <span class="font-medium">{{ item.count }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-teal-500"
                                :style="{ width: percent(item.count, maxDestination) }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card class="border-sidebar-border/70 dark:border-sidebar-border">
            <CardHeader>
                <CardTitle class="text-base">AI requests per day</CardTitle>
                <CardDescription>Last {{ analytics.ai.period_days }} days</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex h-32 items-end gap-0.5">
                    <div
                        v-for="day in analytics.ai.daily"
                        :key="day.date"
                        class="flex h-full flex-1 items-end"
                        :title="`${day.date}: ${day.requests} requests · ${formatCost(day.cost_usd)}`"
                    >
                        <div
                            class="w-full rounded-t bg-violet-500"
                            :style="{ height: percent(day.requests, maxDaily) }"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <CardTitle class="text-base">Usage by feature</CardTitle>
                    <CardDescription>Last {{ analytics.ai.period_days }} days</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p
                        v-if="analytics.ai.by_feature.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No AI usage recorded yet.
                    </p>
                    <div v-for="item in analytics.ai.by_feature" :key="item.feature">
                        <div class="mb-1 flex justify-between gap-2 text-sm">
                            <span>{{ item.label }}</span>
                            <span class="text-muted-foreground">
                                {{ formatCount(item.requests) }} req · {{ formatCount(item.tokens) }} tokens ·
                                {{ formatCost(item.cost_usd) }}
                            </span>
                        </div>
                        <div class="h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-violet-500"
                                :style="{ width: percent(item.requests, maxFeature) }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader>
                    <CardTitle class="text-base">Top AI users</CardTitle>
                    <CardDescription>Last {{ analytics.ai.period_days }} days</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p
                        v-if="analytics.ai.top_users.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No AI usage recorded yet.
                    </p>
                    <div
                        v-for="user in analytics.ai.top_users"
                        :key="user.email"
                        class="flex items-center justify-between gap-2 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ user.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ user.email }}</p>
                        </div>
                        <span class="shrink-0 text-muted-foreground">
                            {{ formatCount(user.requests) }} req · {{ formatCost(user.cost_usd) }}
                        </span>
                    </div>
                </CardContent>
            </Card>
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
