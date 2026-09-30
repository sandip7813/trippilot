<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Globe, Lock, Megaphone } from '@lucide/vue';
import { ref } from 'vue';
import OpenTripDetailsFields from '@/components/OpenTripDetailsFields.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { publish, unpublish } from '@/routes/trips';
import { update as updateExpenseSheetVisibility } from '@/routes/trips/expense-sheet-visibility';
import { update as updateOpenTripDetails } from '@/routes/trips/open-trip';
import type { Trip } from '@/types/trip';

const { trip } = defineProps<{ trip: Trip }>();

const isPublic = () => trip.visibility === 'public';
const isSheetShared = () => trip.expense_sheet_visibility === 'shared';

const publishing = ref(false);

function togglePublish() {
    publishing.value = true;
    const action = isPublic() ? unpublish(trip.id) : publish(trip.id);

    router.post(
        action.url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                publishing.value = false;
            },
        },
    );
}

function toggleExpenseSheetVisibility() {
    router.put(
        updateExpenseSheetVisibility(trip.id).url,
        { expense_sheet_visibility: isSheetShared() ? 'private' : 'shared' },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Card class="card-vibrant overflow-hidden">
        <div class="brand-gradient h-1.5" />
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Megaphone class="size-5" />
                Open trip
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <component
                            :is="isPublic() ? Globe : Lock"
                            class="size-4 text-muted-foreground"
                        />
                        <span class="font-medium">
                            {{
                                isPublic()
                                    ? 'Public — anyone can discover this trip'
                                    : 'Private — only you and collaborators'
                            }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Publishing lets other travelers find this trip on
                        Discover, contact you, and request to join.
                    </p>
                </div>
                <Button
                    :variant="isPublic() ? 'outline' : 'default'"
                    :disabled="publishing"
                    @click="togglePublish"
                >
                    <Spinner v-if="publishing" class="mr-2" />
                    {{ isPublic() ? 'Unpublish' : 'Publish' }}
                </Button>
            </div>

            <Form
                v-bind="updateOpenTripDetails.form(trip.id)"
                v-slot="{ errors, processing, recentlySuccessful }"
                class="space-y-4"
            >
                <OpenTripDetailsFields
                    :details="trip.open_trip"
                    :errors="errors"
                    :trip-start-date="trip.start_date"
                />

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" class="mr-2" />
                        Save group details
                    </Button>
                    <span
                        v-if="recentlySuccessful"
                        class="text-sm text-muted-foreground"
                        >Saved.</span
                    >
                </div>
            </Form>

            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
            >
                <div>
                    <p class="font-medium">Expense sheet visibility</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <Badge variant="secondary" class="mr-1">
                            {{ isSheetShared() ? 'Shared' : 'Private' }}
                        </Badge>
                        {{
                            isSheetShared()
                                ? 'Collaborators can see the expense sheet based on their role.'
                                : 'Only you can see the expense sheet.'
                        }}
                    </p>
                </div>
                <Button variant="outline" @click="toggleExpenseSheetVisibility">
                    {{
                        isSheetShared() ? 'Make private' : 'Share with the trip'
                    }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
