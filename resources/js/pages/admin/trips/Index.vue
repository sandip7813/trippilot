<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Map, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import TripController from '@/actions/App/Http/Controllers/Admin/TripController';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDisplayDateRange } from '@/lib/dates';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as tripsIndex } from '@/routes/admin/trips';
import type { AdminTrip, Paginated } from '@/types/admin';

const props = defineProps<{
    trips: Paginated<AdminTrip>;
    filters: {
        status: string;
        type: string;
        search: string;
    };
    counts: {
        all: number;
        active: number;
        archived: number;
        vacation: number;
        road: number;
    };
}>();

const updatingTripIds = ref<string[]>([]);
const deletingTripIds = ref<string[]>([]);

const selectClass =
    'flex h-9 min-w-32 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

function statusBadgeVariant(
    status: AdminTrip['status'],
): 'default' | 'secondary' | 'outline' {
    if (status === 'planned') {
        return 'default';
    }

    if (status === 'archived') {
        return 'secondary';
    }

    return 'outline';
}

function updateStatus(trip: AdminTrip, status: string): void {
    if (status === trip.status) {
        return;
    }

    updatingTripIds.value.push(trip.id);

    router.patch(
        TripController.updateStatus.url(trip.id),
        { status },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingTripIds.value = updatingTripIds.value.filter(
                    (id) => id !== trip.id,
                );
            },
        },
    );
}

function deleteTrip(trip: AdminTrip): void {
    if (!window.confirm(`Delete "${trip.title}" permanently?`)) {
        return;
    }

    deletingTripIds.value.push(trip.id);

    router.delete(TripController.destroy.url(trip.id), {
        preserveScroll: true,
        onFinish: () => {
            deletingTripIds.value = deletingTripIds.value.filter(
                (id) => id !== trip.id,
            );
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Trips', href: tripsIndex() },
        ],
    },
});
</script>

<template>
    <Head title="Trip moderation" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Trip moderation"
            description="Review trips across all accounts, archive plans, or remove content."
            :icon="Map"
        />

        <Card>
            <CardContent class="p-4">
                <Form :action="tripsIndex()" method="get" class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            :default-value="filters.search"
                            placeholder="Trip title..."
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            name="status"
                            :class="selectClass"
                            :default-value="filters.status"
                        >
                            <option value="all">All ({{ counts.all }})</option>
                            <option value="draft">Draft</option>
                            <option value="planned">Planned</option>
                            <option value="archived">
                                Archived ({{ counts.archived }})
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="type">Type</Label>
                        <select
                            id="type"
                            name="type"
                            :class="selectClass"
                            :default-value="filters.type"
                        >
                            <option value="all">All types</option>
                            <option value="vacation">
                                Vacation ({{ counts.vacation }})
                            </option>
                            <option value="road">
                                Road ({{ counts.road }})
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <Button type="submit" class="w-full">Filter</Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <div
                    v-if="trips.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No trips match your filters.
                </div>

                <div v-else class="divide-y divide-border/60">
                    <div
                        v-for="trip in trips.data"
                        :key="trip.id"
                        class="flex flex-col gap-4 p-4 xl:flex-row xl:items-center xl:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium">{{ trip.title }}</p>
                                <Badge variant="outline" class="text-xs">
                                    {{ trip.type_label }}
                                </Badge>
                                <Badge
                                    :variant="statusBadgeVariant(trip.status)"
                                    class="text-xs"
                                >
                                    {{ trip.status_label }}
                                </Badge>
                            </div>
                            <p
                                v-if="trip.destination_label"
                                class="text-sm text-muted-foreground"
                            >
                                {{ trip.destination_label }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    formatDisplayDateRange(
                                        trip.start_date,
                                        trip.end_date,
                                    )
                                }}
                                · Owner
                                {{
                                    trip.owner.name ??
                                    trip.owner.email ??
                                    `#${trip.owner.id}`
                                }}
                            </p>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-2 self-start xl:self-center"
                        >
                            <Spinner
                                v-if="
                                    updatingTripIds.includes(trip.id) ||
                                    deletingTripIds.includes(trip.id)
                                "
                                class="size-4"
                            />

                            <Button variant="outline" size="sm" as-child>
                                <Link :href="trip.show_url" target="_blank">
                                    <ExternalLink class="mr-2 size-4" />
                                    View
                                </Link>
                            </Button>

                            <select
                                :value="trip.status"
                                :disabled="
                                    updatingTripIds.includes(trip.id) ||
                                    deletingTripIds.includes(trip.id)
                                "
                                :class="selectClass"
                                @change="
                                    updateStatus(
                                        trip,
                                        (
                                            $event.target as HTMLSelectElement
                                        ).value,
                                    )
                                "
                            >
                                <option value="draft">Draft</option>
                                <option value="planned">Planned</option>
                                <option value="archived">Archived</option>
                            </select>

                            <Button
                                variant="destructive"
                                size="sm"
                                :disabled="
                                    deletingTripIds.includes(trip.id) ||
                                    updatingTripIds.includes(trip.id)
                                "
                                @click="deleteTrip(trip)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="trips.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ trips.from ?? 0 }}–{{ trips.to ?? 0 }} of
                {{ trips.total }}
            </p>

            <div class="flex flex-wrap gap-2">
                <template
                    v-for="link in trips.links"
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

        <Button variant="outline" as-child>
            <Link :href="adminDashboard()">
                <ArrowLeft class="mr-2 size-4" />
                Back to admin
            </Link>
        </Button>
    </div>
</template>
