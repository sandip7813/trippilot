<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ExternalLink, ShieldAlert } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { dismiss, takedown } from '@/routes/admin/trip-reports';

type Report = {
    id: string;
    reason: string;
    message: string | null;
    created_at: string | null;
    reporter_name: string | null;
    trip: { id: string; title: string; is_public: boolean; show_url: string } | null;
};

defineProps<{
    reports: Report[];
}>();
</script>

<template>
    <Head title="Reported trips" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Reported trips"
            description="Open reports filed against public open trips"
            :icon="ShieldAlert"
        />

        <EmptyState
            v-if="reports.length === 0"
            :icon="ShieldAlert"
            title="No open reports"
            description="Reports travelers file against public trips show up here for review."
        />

        <Card v-for="report in reports" :key="report.id" class="card-vibrant overflow-hidden">
            <div class="brand-gradient h-1" />
            <CardContent class="space-y-3 pt-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ report.trip?.title ?? 'Deleted trip' }}</h2>
                    <Badge variant="secondary">{{ report.reason }}</Badge>
                </div>
                <p v-if="report.message" class="text-sm">{{ report.message }}</p>
                <p class="text-xs text-muted-foreground">
                    Reported by {{ report.reporter_name ?? 'a user' }}
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="report.trip" variant="outline" size="sm" as-child>
                        <Link :href="report.trip.show_url" target="_blank">
                            <ExternalLink class="mr-2 size-4" />
                            View trip
                        </Link>
                    </Button>
                    <Form v-if="report.trip" v-bind="takedown.form(report.id)" v-slot="{ processing }">
                        <Button type="submit" variant="destructive" size="sm" :disabled="processing">
                            <Spinner v-if="processing" class="mr-2" />
                            Unpublish trip
                        </Button>
                    </Form>
                    <Form v-bind="dismiss.form(report.id)" v-slot="{ processing }">
                        <Button type="submit" variant="ghost" size="sm" :disabled="processing">
                            <Spinner v-if="processing" class="mr-2" />
                            Dismiss
                        </Button>
                    </Form>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
