<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useExpenseRequest } from '@/composables/useExpenseRequest';
import { cn } from '@/lib/utils';
import { EXPENSE_CATEGORIES } from '@/types/expenses';
import type {
    ExpenseEntry,
    ExpenseEntryType,
    ExpenseParticipant,
    ExpenseSplitType,
} from '@/types/expenses';

export type ExpenseDialogPrefill = {
    type?: ExpenseEntryType;
    title?: string;
    entry_date?: string;
    category?: string;
    amount?: string;
    payer_id?: string;
};

type PayerRow = { participant_id: string; amount: string };
type SplitRow = { participant_id: string; included: boolean; value: string };

const props = defineProps<{
    tripId: string;
    participants: ExpenseParticipant[];
    entry: ExpenseEntry | null;
    prefill?: ExpenseDialogPrefill | null;
}>();

const open = defineModel<boolean>('open', { default: false });
const { processing, errors, send } = useExpenseRequest();

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const entryTypes: { value: ExpenseEntryType; label: string }[] = [
    { value: 'payment', label: 'Payment' },
    { value: 'refund', label: 'Refund' },
    { value: 'transfer', label: 'Paid personally' },
];

const splitTypes: { value: ExpenseSplitType; label: string }[] = [
    { value: 'equal', label: 'Equally' },
    { value: 'exact', label: 'Exact amounts' },
    { value: 'percentage', label: 'Percentages' },
    { value: 'shares', label: 'Shares' },
];

const type = ref<ExpenseEntryType>('payment');
const category = ref('food');
const title = ref('');
const entryDate = ref('');
const amount = ref('');
const notes = ref('');
const splitType = ref<ExpenseSplitType>('equal');
const payers = ref<PayerRow[]>([]);
const splitRows = ref<SplitRow[]>([]);
const transferTo = ref('');

const selectable = computed(() =>
    props.participants.filter(
        (participant) =>
            !participant.archived ||
            props.entry?.payers.some(
                (row) => row.participant_id === participant.id,
            ) ||
            props.entry?.splits.some(
                (row) => row.participant_id === participant.id,
            ),
    ),
);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    const entry = props.entry;
    const first = selectable.value[0]?.id ?? '';

    const prefill = props.prefill ?? {};

    type.value = prefill.type ?? entry?.type ?? 'payment';
    category.value = prefill.category ?? entry?.category ?? 'food';
    title.value = prefill.title ?? entry?.title ?? '';
    entryDate.value =
        prefill.entry_date ??
        entry?.entry_date ??
        new Date().toLocaleDateString('en-CA');
    amount.value = prefill.amount ?? (entry ? String(entry.amount) : '');
    notes.value = entry?.notes ?? '';
    splitType.value = entry?.split_type ?? 'equal';
    payers.value = entry
        ? entry.payers.map((row) => ({
              participant_id: row.participant_id,
              amount: String(row.amount),
          }))
        : [{ participant_id: prefill.payer_id ?? first, amount: '' }];
    transferTo.value =
        entry?.type === 'transfer'
            ? (entry.splits[0]?.participant_id ?? '')
            : '';
    splitRows.value = selectable.value.map((participant) => {
        const input = entry?.split_inputs.find(
            (row) => row.participant_id === participant.id,
        );

        return {
            participant_id: participant.id,
            included: entry ? input !== undefined : true,
            value: input?.value != null ? String(input.value) : '',
        };
    });
});

const isTransfer = computed(() => type.value === 'transfer');
const total = computed(() => Number(amount.value) || 0);
const includedRows = computed(() =>
    splitRows.value.filter((row) => row.included),
);
const payersTotal = computed(() =>
    payers.value.reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
);
const splitValueTotal = computed(() =>
    includedRows.value.reduce((sum, row) => sum + (Number(row.value) || 0), 0),
);

const splitHint = computed(() => {
    if (splitType.value === 'exact') {
        return `${splitValueTotal.value.toFixed(2)} of ${total.value.toFixed(2)} assigned`;
    }

    if (splitType.value === 'percentage') {
        return `${splitValueTotal.value.toFixed(2)}% of 100% assigned`;
    }

    return null;
});

const labels = computed(() => {
    if (type.value === 'refund') {
        return {
            payers: 'Refund received by',
            split: 'Credit goes to',
            amount: 'Refund amount',
            title: 'What was refunded?',
        };
    }

    if (type.value === 'transfer') {
        return {
            payers: 'Paid by',
            split: 'Paid to',
            amount: 'Amount',
            title: 'Note',
        };
    }

    return {
        payers: 'Paid by',
        split: 'Split between',
        amount: 'Total amount',
        title: 'What was it for?',
    };
});

function name(participantId: string): string {
    return (
        props.participants.find(
            (participant) => participant.id === participantId,
        )?.name ?? 'Unknown'
    );
}

function addPayer(): void {
    const used = new Set(payers.value.map((row) => row.participant_id));
    const next = selectable.value.find(
        (participant) => !used.has(participant.id),
    );

    if (next) {
        payers.value.push({ participant_id: next.id, amount: '' });
    }
}

function submit(): void {
    const singlePayer = payers.value.length === 1;
    const payload = {
        type: type.value,
        category: isTransfer.value ? null : category.value,
        title: title.value,
        entry_date: entryDate.value,
        amount: amount.value,
        notes: notes.value,
        payers: payers.value.map((row) => ({
            participant_id: row.participant_id,
            amount: singlePayer ? amount.value : row.amount,
        })),
        split_type: isTransfer.value ? 'exact' : splitType.value,
        split_inputs: isTransfer.value
            ? [{ participant_id: transferTo.value }]
            : includedRows.value.map((row) => ({
                  participant_id: row.participant_id,
                  value: splitType.value === 'equal' ? null : row.value,
              })),
    };
    const onSuccess = () => {
        open.value = false;
    };

    if (props.entry === null) {
        send(
            'post',
            TripExpenseController.storeEntry.url({ trip: props.tripId }),
            payload,
            onSuccess,
        );

        return;
    }

    send(
        'patch',
        TripExpenseController.updateEntry.url({
            trip: props.tripId,
            entry: props.entry.id,
        }),
        payload,
        onSuccess,
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ entry ? 'Edit entry' : 'Add an entry' }}
                </DialogTitle>
                <DialogDescription>
                    <template v-if="type === 'payment'">
                        Record money someone paid, and how the cost is shared.
                    </template>
                    <template v-else-if="type === 'refund'">
                        Record money that came back, for example a cancelled
                        ticket, and who receives it.
                    </template>
                    <template v-else>
                        Record one person paying another directly. It settles
                        what they owe without counting as trip spend.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="entry === null"
                class="inline-flex w-fit gap-1 rounded-lg bg-muted/60 p-1"
                role="tablist"
            >
                <button
                    v-for="option in entryTypes"
                    :key="option.value"
                    type="button"
                    role="tab"
                    :aria-selected="type === option.value"
                    :class="
                        cn(
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            type === option.value
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )
                    "
                    @click="type = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="entry-title">{{ labels.title }}</Label>
                        <Input id="entry-title" v-model="title" />
                        <InputError :message="errors.title" />
                    </div>
                    <div v-if="!isTransfer" class="grid gap-1.5">
                        <Label for="entry-category">Category</Label>
                        <select
                            id="entry-category"
                            v-model="category"
                            :class="selectClass"
                        >
                            <option
                                v-for="option in EXPENSE_CATEGORIES"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="entry-date">Date</Label>
                        <Input
                            id="entry-date"
                            v-model="entryDate"
                            type="date"
                        />
                        <InputError :message="errors.entry_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="entry-amount">{{ labels.amount }}</Label>
                        <Input
                            id="entry-amount"
                            v-model="amount"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="errors.amount" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="entry-notes">Notes (optional)</Label>
                        <Input id="entry-notes" v-model="notes" />
                    </div>
                </div>

                <fieldset
                    class="grid gap-2 rounded-lg border border-border/60 p-3"
                >
                    <legend class="px-1 text-sm font-semibold">
                        {{ labels.payers }}
                    </legend>
                    <div
                        v-for="(row, index) in payers"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <select
                            v-model="row.participant_id"
                            :class="selectClass"
                            :aria-label="labels.payers"
                        >
                            <option
                                v-for="participant in selectable"
                                :key="participant.id"
                                :value="participant.id"
                            >
                                {{ participant.name }}
                            </option>
                        </select>
                        <Input
                            v-if="payers.length > 1"
                            v-model="row.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-32"
                            aria-label="Amount paid"
                        />
                        <Button
                            v-if="payers.length > 1"
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Remove payer"
                            @click="payers.splice(index, 1)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            v-if="
                                !isTransfer && payers.length < selectable.length
                            "
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addPayer"
                        >
                            <Plus class="size-4" />
                            Another person
                        </Button>
                        <span
                            v-if="payers.length > 1"
                            :class="
                                cn(
                                    'text-xs',
                                    Math.abs(payersTotal - total) < 0.005
                                        ? 'text-muted-foreground'
                                        : 'text-destructive',
                                )
                            "
                        >
                            {{ payersTotal.toFixed(2) }} of
                            {{ total.toFixed(2) }} covered
                        </span>
                    </div>
                    <InputError :message="errors.payers" />
                </fieldset>

                <fieldset
                    v-if="isTransfer"
                    class="grid gap-2 rounded-lg border border-border/60 p-3"
                >
                    <legend class="px-1 text-sm font-semibold">
                        {{ labels.split }}
                    </legend>
                    <select
                        v-model="transferTo"
                        :class="selectClass"
                        :aria-label="labels.split"
                    >
                        <option value="" disabled>Choose a person</option>
                        <option
                            v-for="participant in selectable"
                            :key="participant.id"
                            :value="participant.id"
                        >
                            {{ participant.name }}
                        </option>
                    </select>
                    <InputError
                        :message="errors['split_inputs.0.participant_id']"
                    />
                </fieldset>

                <fieldset
                    v-else
                    class="grid gap-2 rounded-lg border border-border/60 p-3"
                >
                    <legend class="px-1 text-sm font-semibold">
                        {{ labels.split }}
                    </legend>
                    <select
                        v-model="splitType"
                        :class="cn(selectClass, 'sm:w-56')"
                        aria-label="How to split"
                    >
                        <option
                            v-for="option in splitTypes"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <div
                        v-for="row in splitRows"
                        :key="row.participant_id"
                        class="flex items-center gap-3"
                    >
                        <label
                            class="flex min-w-0 flex-1 items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :model-value="row.included"
                                @update:model-value="
                                    row.included = $event === true
                                "
                            />
                            <span class="truncate">{{
                                name(row.participant_id)
                            }}</span>
                        </label>
                        <Input
                            v-if="splitType !== 'equal' && row.included"
                            v-model="row.value"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-28"
                            :aria-label="`Value for ${name(row.participant_id)}`"
                            :placeholder="
                                splitType === 'percentage'
                                    ? '%'
                                    : splitType === 'shares'
                                      ? 'shares'
                                      : 'amount'
                            "
                        />
                    </div>
                    <p v-if="splitHint" class="text-xs text-muted-foreground">
                        {{ splitHint }}
                    </p>
                    <InputError :message="errors.split_inputs" />
                </fieldset>

                <InputError :message="errors.expense" />

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
                        {{ entry ? 'Save changes' : 'Add entry' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
