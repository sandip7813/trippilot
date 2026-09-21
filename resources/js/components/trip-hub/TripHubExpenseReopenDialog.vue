<script setup lang="ts">
import { ref, watch } from 'vue';
import TripExpenseController from '@/actions/App/Http/Controllers/TripExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useExpenseRequest } from '@/composables/useExpenseRequest';

const props = defineProps<{
    tripId: string;
    participantCount: number;
}>();

const open = defineModel<boolean>('open', { default: false });
const { processing, errors, send } = useExpenseRequest();
const reason = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        reason.value = '';
    }
});

function submit(): void {
    send(
        'post',
        TripExpenseController.reopen.url({ trip: props.tripId }),
        { reason: reason.value },
        () => {
            open.value = false;
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Reopen the settled sheet?</DialogTitle>
                <DialogDescription>
                    All {{ participantCount }} participants will be emailed that
                    the sheet was reopened, along with your reason.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="reopen-reason">Reason</Label>
                    <Input
                        id="reopen-reason"
                        v-model="reason"
                        maxlength="500"
                        placeholder="e.g. Forgot to add the taxi fare"
                    />
                    <InputError :message="errors.reason ?? errors.expense" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" class="size-4" />
                        Reopen sheet
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
