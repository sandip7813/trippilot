<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Calendar,
    Globe,
    ListChecks,
    Lock,
    MapPin,
    Megaphone,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDisplayDate } from '@/lib/dates';
import { edit } from '@/routes/trips';
import type { OpenTripCostModel, Trip } from '@/types/trip';

const { trip } = defineProps<{ trip: Trip }>();

const isPublic = computed(() => trip.visibility === 'public');
const isSheetShared = computed(
    () => trip.expense_sheet_visibility === 'shared',
);

const details = computed(() => trip.open_trip ?? null);

function humanize(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

const costModelLabels: Record<OpenTripCostModel, string> = {
    cost_sharing: 'Cost sharing',
    fixed_price: 'Fixed price',
    pay_own: 'Everyone pays their own',
};

const costModelLabel = computed(() => {
    const model = details.value?.cost_model;

    return model ? costModelLabels[model] : null;
});

const acceptedMemberCount = computed(
    () =>
        trip.collaborators.filter(
            (collaborator) =>
                collaborator.role === 'member' &&
                collaborator.status === 'accepted',
        ).length,
);

const seatsLeft = computed(() => {
    const max = details.value?.max_group_size;

    return max !== null && max !== undefined
        ? Math.max(0, max - acceptedMemberCount.value)
        : null;
});
</script>

<template>
    <Card class="card-vibrant overflow-hidden">
        <div class="brand-gradient h-1.5" />
        <CardHeader
            class="flex flex-row items-center justify-between gap-3 space-y-0"
        >
            <CardTitle class="flex items-center gap-2">
                <Megaphone class="size-5" />
                Open trip
            </CardTitle>
            <Button variant="outline" size="sm" as-child>
                <Link :href="edit(trip.id)">Manage</Link>
            </Button>
        </CardHeader>
        <CardContent class="space-y-6">
            <div
                class="flex flex-wrap items-center gap-3 rounded-lg border p-4"
            >
                <component
                    :is="isPublic ? Globe : Lock"
                    class="size-4 shrink-0 text-muted-foreground"
                />
                <div>
                    <span class="font-medium">
                        {{
                            isPublic
                                ? 'Public — anyone can discover this trip'
                                : 'Private — only you and collaborators'
                        }}
                    </span>
                    <p
                        v-if="isPublic && trip.published_at"
                        class="mt-0.5 text-sm text-muted-foreground"
                    >
                        Published
                        {{ formatDisplayDate(trip.published_at.slice(0, 10)) }}
                    </p>
                </div>
            </div>

            <div v-if="details" class="grid gap-4 sm:grid-cols-2">
                <div
                    v-if="humanize(details.category)"
                    class="flex items-start gap-3"
                >
                    <Megaphone
                        class="mt-0.5 size-4 shrink-0 text-teal-600 dark:text-teal-400"
                    />
                    <div>
                        <p class="text-xs text-muted-foreground">Category</p>
                        <p class="text-sm font-medium">
                            {{ humanize(details.category) }}
                            <span
                                v-if="humanize(details.difficulty)"
                                class="text-muted-foreground"
                            >
                                · {{ humanize(details.difficulty) }}
                            </span>
                        </p>
                    </div>
                </div>

                <div
                    v-if="details.max_group_size"
                    class="flex items-start gap-3"
                >
                    <Users
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <div>
                        <p class="text-xs text-muted-foreground">Group size</p>
                        <p class="text-sm font-medium">
                            {{ seatsLeft }} of
                            {{ details.max_group_size }} seats left
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ acceptedMemberCount }} traveler{{
                                acceptedMemberCount === 1 ? '' : 's'
                            }}
                            joined
                        </p>
                    </div>
                </div>

                <div
                    v-if="details.join_deadline"
                    class="flex items-start gap-3"
                >
                    <Calendar
                        class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-400"
                    />
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Join deadline
                        </p>
                        <p class="text-sm font-medium">
                            {{
                                formatDisplayDate(
                                    details.join_deadline.slice(0, 10),
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div v-if="costModelLabel" class="flex items-start gap-3">
                    <ShieldCheck
                        class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    <div>
                        <p class="text-xs text-muted-foreground">Cost</p>
                        <p class="text-sm font-medium">
                            {{ costModelLabel }}
                            <span v-if="details.cost_amount">
                                — {{ details.cost_currency }}
                                {{ details.cost_amount }} / person
                            </span>
                        </p>
                    </div>
                </div>

                <div
                    v-if="details.meeting_point"
                    class="flex items-start gap-3"
                >
                    <MapPin
                        class="mt-0.5 size-4 shrink-0 text-violet-600 dark:text-violet-400"
                    />
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Meeting point
                        </p>
                        <p class="text-sm font-medium">
                            {{ details.meeting_point }}
                        </p>
                    </div>
                </div>
            </div>

            <p v-if="details?.requirements" class="text-sm leading-relaxed">
                <span class="font-medium">Requirements: </span
                >{{ details.requirements }}
            </p>
            <p v-if="details?.cost_inclusions" class="text-sm leading-relaxed">
                <span class="font-medium">Included: </span
                >{{ details.cost_inclusions }}
            </p>
            <p v-if="details?.rules" class="text-sm leading-relaxed">
                <span class="font-medium">Group rules: </span
                >{{ details.rules }}
            </p>

            <div class="flex flex-wrap items-center gap-2 border-t pt-4">
                <ListChecks class="size-4 shrink-0 text-muted-foreground" />
                <span class="text-sm text-muted-foreground"
                    >Expense sheet:</span
                >
                <Badge variant="secondary">{{
                    isSheetShared ? 'Shared' : 'Private'
                }}</Badge>
            </div>
        </CardContent>
    </Card>
</template>
