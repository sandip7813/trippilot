<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Bot, Shield, Users } from '@lucide/vue';
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
            <StatCard
                label="Users"
                value="—"
                hint="User management in Phase 6"
                :icon="Users"
                accent="primary"
            />
            <StatCard
                label="Trips"
                value="—"
                hint="Across all accounts"
                :icon="Shield"
                accent="sky"
            />
            <StatCard
                label="AI Requests"
                value="—"
                hint="Gemini usage tracking"
                :icon="Bot"
                accent="violet"
            />
        </div>

        <Card class="border-sidebar-border/70 dark:border-sidebar-border">
            <CardHeader>
                <CardTitle class="text-base">Platform status</CardTitle>
                <CardDescription>
                    Admin features are being built incrementally. Full stats,
                    user management, and integration controls arrive in Phase 6.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        class="rounded-lg border border-border/60 bg-muted/30 p-4"
                    >
                        <p class="text-sm font-medium">Foundation</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Roles, middleware, and admin routes are in place.
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
                        <p class="text-sm font-medium">Management tools</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            User lists, trip moderation, and AI usage
                            dashboards.
                        </p>
                        <span
                            class="mt-3 inline-flex rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                        >
                            Phase 6
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
