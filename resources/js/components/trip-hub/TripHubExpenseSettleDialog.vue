<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { ref, watch } from 'vue';
import TripExpenseController from '@/actions/App/Http/Controllers/TripExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useExpenseRequest } from '@/composables/useExpenseRequest';

const props = defineProps<{
    tripId: string;
    outstanding: { text: string }[];
}>();

const open = defineModel<boolean>('open', { default: false });
const { processing, errors, send } = useExpenseRequest();
const notify = ref(true);

watch(open, (isOpen) => {
    if (isOpen) {
        notify.value = true;
    }
});

function submit(): void {
    send(
        'post',
        TripExpenseController.settle.url({ trip: props.tripId }),
        { notify_participants: notify.value },
        () => {
            open.value = false;
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Close and settle this sheet?</DialogTitle>
                <DialogDescription>
                    The sheet becomes read-only and the final figures are saved.
                    You can reopen it later, which notifies every participant.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="outstanding.length > 0"
                class="space-y-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm"
            >
                <p class="flex items-center gap-2 font-semibold">
                    <TriangleAlert class="size-4 text-amber-600" />
                    Some balances are not settled yet
                </p>
                <ul class="list-disc space-y-0.5 pl-5 text-muted-foreground">
                    <li v-for="line in outstanding" :key="line.text">
                        {{ line.text }}
                    </li>
                </ul>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <Checkbox
                    id="notify-on-settle"
                    :model-value="notify"
                    @update:model-value="notify = $event === true"
                />
                <Label for="notify-on-settle" class="font-normal">
                    Email a summary to all participants
                </Label>
            </label>

            <InputError :message="errors.expense" />

            <DialogFooter>
                <Button type="button" variant="outline" @click="open = false">
                    Cancel
                </Button>
                <Button type="button" :disabled="processing" @click="submit">
                    <Spinner v-if="processing" class="size-4" />
                    Settle and lock
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
