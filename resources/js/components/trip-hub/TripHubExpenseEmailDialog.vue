<script setup lang="ts">
import { computed, ref, watch } from 'vue';
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
import type { ExpenseParticipant } from '@/types/expenses';

const props = defineProps<{
    tripId: string;
    participants: ExpenseParticipant[];
}>();

const open = defineModel<boolean>('open', { default: false });
const { processing, errors, send } = useExpenseRequest();

const recipients = ref('');
const format = ref<'pdf' | 'csv'>('pdf');
const message = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        recipients.value = '';
        format.value = 'pdf';
        message.value = '';
    }
});

const parsedRecipients = computed(() =>
    recipients.value
        .split(/[\s,;]+/)
        .map((email) => email.trim())
        .filter((email) => email !== ''),
);

const recipientError = computed(
    () =>
        errors.value.recipients ??
        Object.entries(errors.value).find(([key]) =>
            key.startsWith('recipients.'),
        )?.[1] ??
        errors.value.expense,
);

function addParticipant(participant: ExpenseParticipant): void {
    if (
        !parsedRecipients.value.some(
            (email) => email.toLowerCase() === participant.email,
        )
    ) {
        recipients.value = [...parsedRecipients.value, participant.email].join(
            ', ',
        );
    }
}

function submit(): void {
    send(
        'post',
        TripExpenseController.emailReport.url({ trip: props.tripId }),
        {
            recipients: parsedRecipients.value,
            format: format.value,
            message: message.value === '' ? null : message.value,
        },
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
                <DialogTitle>Email the expense report</DialogTitle>
                <DialogDescription>
                    Send the report to up to 5 email addresses. It includes
                    names, amounts and the activity log, so only send it to
                    people who should see them.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="report-recipients">To</Label>
                    <Input
                        id="report-recipients"
                        v-model="recipients"
                        placeholder="name@example.com, other@example.com"
                    />
                    <div class="flex flex-wrap gap-1.5">
                        <Button
                            v-for="participant in participants.filter(
                                (person) => !person.archived,
                            )"
                            :key="participant.id"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addParticipant(participant)"
                        >
                            {{ participant.name }}
                        </Button>
                    </div>
                    <InputError :message="recipientError ?? undefined" />
                </div>

                <div class="grid gap-1.5">
                    <Label>Format</Label>
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="format" type="radio" value="pdf" />
                            PDF
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="format" type="radio" value="csv" />
                            CSV (spreadsheet)
                        </label>
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="report-message">Message (optional)</Label>
                    <Input
                        id="report-message"
                        v-model="message"
                        maxlength="500"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="processing || parsedRecipients.length === 0"
                    >
                        <Spinner v-if="processing" class="size-4" />
                        Send report
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
