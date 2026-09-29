<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, UserPlus } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { show as showTrip } from '@/routes/trips';
import { accept, decline } from '@/routes/trips/join-requests';

type JoinRequestRow = {
    id: string;
    status: string;
    status_label: string;
    travelers_count: number;
    phone: string | null;
    message: string | null;
    requester_name: string;
    created_at: string | null;
};

const { trip, requests } = defineProps<{
    trip: { id: string; title: string; max_group_size: number | null; seats_left: number | null };
    requests: JoinRequestRow[];
}>();

const badgeVariant = (status: string) => {
    if (status === 'accepted') {
return 'default';
}

    if (status === 'declined' || status === 'withdrawn') {
return 'outline';
}

    return 'secondary';
};
</script>

<template>
    <Head :title="`Join requests — ${trip.title}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Join requests"
            :description="`People asking to join ${trip.title}`"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="showTrip(trip.id)">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to trip
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <p v-if="trip.max_group_size" class="text-sm text-muted-foreground">
            {{ trip.seats_left }} of {{ trip.max_group_size }} seats left.
        </p>

        <EmptyState
            v-if="requests.length === 0"
            :icon="UserPlus"
            title="No join requests yet"
            description="Requests from travelers who want to join this trip show up here."
        />

        <Card v-for="request in requests" :key="request.id" class="card-vibrant overflow-hidden">
            <div class="brand-gradient h-1" />
            <CardContent class="space-y-3 pt-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ request.requester_name }}</h2>
                    <Badge :variant="badgeVariant(request.status)">{{ request.status_label }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ request.travelers_count }} traveler(s)
                    <span v-if="request.phone"> · {{ request.phone }}</span>
                </p>
                <p v-if="request.message" class="text-sm">{{ request.message }}</p>

                <div v-if="request.status === 'pending'" class="flex gap-2">
                    <Form v-bind="accept.form([trip.id, request.id])" v-slot="{ processing }">
                        <Button type="submit" size="sm" :disabled="processing">
                            <Spinner v-if="processing" class="mr-2" />
                            Accept
                        </Button>
                    </Form>
                    <Form v-bind="decline.form([trip.id, request.id])" v-slot="{ processing }">
                        <Button type="submit" size="sm" variant="outline" :disabled="processing">
                            <Spinner v-if="processing" class="mr-2" />
                            Decline
                        </Button>
                    </Form>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
