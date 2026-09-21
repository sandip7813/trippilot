<script setup lang="ts">
import { Check, Plus, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import TripExpenseController from '@/actions/App/Http/Controllers/TripExpenseController';
import type { ExpenseDialogPrefill } from '@/components/trip-hub/TripHubExpenseEntryDialog.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useExpenseRequest } from '@/composables/useExpenseRequest';
import { EXPENSE_CATEGORIES } from '@/types/expenses';
import type {
    ExpenseEntry,
    ExpenseEntryType,
    ExpenseParticipant,
} from '@/types/expenses';

const EVERYONE = '__everyone__';
const MORE = '__more__';

const props = defineProps<{
    tripId: string;
    participants: ExpenseParticipant[];
    entry?: ExpenseEntry | null;
}>();

const emit = defineEmits<{
    done: [];
    cancel: [];
    advanced: [prefill: ExpenseDialogPrefill];
}>();

const { processing, errors, send } = useExpenseRequest();

const cell =
    'h-8 w-full min-w-0 rounded-md border border-input bg-background px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/50';

const everyone = computed(() =>
    props.participants.filter((participant) => !participant.archived),
);

/** Archived people stay selectable only when the edited row already uses them. */
const active = computed(() => {
    const used = new Set([
        ...(props.entry?.payers ?? []).map((row) => row.participant_id),
        ...(props.entry?.splits ?? []).map((row) => row.participant_id),
    ]);

    return props.participants.filter(
        (participant) => !participant.archived || used.has(participant.id),
    );
});

function initialSplit(entry: ExpenseEntry | null | undefined): string {
    if (!entry || entry.type === 'transfer') {
        return EVERYONE;
    }

    const ids = entry.splits.map((row) => row.participant_id);

    return ids.length === 1 ? ids[0] : EVERYONE;
}

const type = ref<ExpenseEntryType>(props.entry?.type ?? 'payment');
const date = ref(
    props.entry?.entry_date ?? new Date().toLocaleDateString('en-CA'),
);
const title = ref(props.entry?.title ?? '');
const category = ref(props.entry?.category ?? 'food');
const amount = ref(props.entry ? String(props.entry.amount) : '');
const paidBy = ref(
    props.entry?.payers[0]?.participant_id ?? active.value[0]?.id ?? '',
);
const split = ref(initialSplit(props.entry));
const transferTo = ref(
    props.entry?.type === 'transfer'
        ? (props.entry.splits[0]?.participant_id ?? '')
        : '',
);
const titleInput = ref<HTMLInputElement | null>(null);

const isTransfer = computed(() => type.value === 'transfer');
const isEditing = computed(() => Boolean(props.entry));

const paidByLabel = computed(() => {
    if (type.value === 'refund') {
        return 'Received by';
    }

    return 'Paid by';
});

function openAdvanced(): void {
    emit('advanced', {
        type: type.value,
        title: title.value,
        entry_date: date.value,
        category: category.value,
        amount: amount.value,
        payer_id: paidBy.value,
    });
}

function onPaidByChange(event: Event): void {
    const select = event.target as HTMLSelectElement;

    if (select.value === MORE) {
        select.value = paidBy.value;
        openAdvanced();

        return;
    }

    paidBy.value = select.value;
}

function onSplitChange(event: Event): void {
    const select = event.target as HTMLSelectElement;

    if (select.value === MORE) {
        select.value = split.value;
        openAdvanced();

        return;
    }

    split.value = select.value;
}

function splitInputs(): { participant_id: string; value: null }[] {
    if (isTransfer.value) {
        return [{ participant_id: transferTo.value, value: null }];
    }

    const ids =
        split.value === EVERYONE
            ? everyone.value.map((participant) => participant.id)
            : [split.value];

    return ids.map((id) => ({ participant_id: id, value: null }));
}

function submit(): void {
    const url = props.entry
        ? TripExpenseController.updateEntry.url({
              trip: props.tripId,
              entry: props.entry.id,
          })
        : TripExpenseController.storeEntry.url({ trip: props.tripId });

    send(
        props.entry ? 'patch' : 'post',
        url,
        {
            type: type.value,
            category: isTransfer.value ? null : category.value,
            title: title.value,
            entry_date: date.value,
            amount: amount.value,
            notes: props.entry?.notes ?? null,
            payers: [{ participant_id: paidBy.value, amount: amount.value }],
            split_type: isTransfer.value ? 'exact' : 'equal',
            split_inputs: splitInputs(),
        },
        () => {
            if (isEditing.value) {
                emit('done');

                return;
            }

            title.value = '';
            amount.value = '';
            void nextTick(() => titleInput.value?.focus());
        },
    );
}

const firstError = computed(
    () => Object.values(errors.value)[0] as string | undefined,
);
</script>

<template>
    <tbody :class="isEditing ? 'bg-amber-500/5' : 'bg-primary/5'">
        <tr class="align-top" @keydown.enter.prevent="submit">
            <td class="px-2 py-1.5">
                <select v-model="type" :class="cell" aria-label="Type">
                    <option value="payment">Expense</option>
                    <option value="refund">Refund</option>
                    <option value="transfer">Paid personally</option>
                </select>
            </td>
            <td class="px-2 py-1.5">
                <input
                    v-model="date"
                    type="date"
                    :class="cell"
                    aria-label="Date"
                />
            </td>
            <td class="px-2 py-1.5">
                <input
                    ref="titleInput"
                    v-model="title"
                    type="text"
                    maxlength="150"
                    :placeholder="
                        isTransfer ? 'Note (optional)' : 'What was it for?'
                    "
                    :class="cell"
                    aria-label="What"
                />
            </td>
            <td class="px-2 py-1.5">
                <select
                    v-if="!isTransfer"
                    v-model="category"
                    :class="cell"
                    aria-label="Category"
                >
                    <option
                        v-for="option in EXPENSE_CATEGORIES"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </td>
            <td class="px-2 py-1.5">
                <input
                    v-model="amount"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    :class="[cell, 'text-right']"
                    aria-label="Amount"
                />
            </td>
            <td class="px-2 py-1.5">
                <select
                    :value="paidBy"
                    :class="cell"
                    :aria-label="paidByLabel"
                    @change="onPaidByChange"
                >
                    <option
                        v-for="participant in active"
                        :key="participant.id"
                        :value="participant.id"
                        :selected="participant.id === paidBy"
                    >
                        {{ participant.name }}
                    </option>
                    <option v-if="!isTransfer" :value="MORE">
                        Several people…
                    </option>
                </select>
            </td>
            <td class="px-2 py-1.5">
                <select
                    v-if="isTransfer"
                    v-model="transferTo"
                    :class="cell"
                    aria-label="Paid to"
                >
                    <option value="" disabled>Paid to…</option>
                    <option
                        v-for="participant in active.filter(
                            (person) => person.id !== paidBy,
                        )"
                        :key="participant.id"
                        :value="participant.id"
                    >
                        {{ participant.name }}
                    </option>
                </select>
                <select
                    v-else
                    :value="split"
                    :class="cell"
                    aria-label="Split between"
                    @change="onSplitChange"
                >
                    <option :value="EVERYONE" :selected="split === EVERYONE">
                        Everyone, equally
                    </option>
                    <option
                        v-for="participant in active"
                        :key="participant.id"
                        :value="participant.id"
                        :selected="participant.id === split"
                    >
                        Only {{ participant.name }}
                    </option>
                    <option :value="MORE">Choose people / custom…</option>
                </select>
            </td>
            <td class="px-2 py-1.5">
                <div class="flex items-center justify-end gap-1">
                    <Button
                        type="button"
                        size="icon-sm"
                        :aria-label="isEditing ? 'Save row' : 'Add row'"
                        :disabled="processing"
                        @click="submit"
                    >
                        <Spinner v-if="processing" class="size-4" />
                        <Check v-else-if="isEditing" class="size-4" />
                        <Plus v-else class="size-4" />
                    </Button>
                    <Button
                        v-if="isEditing"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Cancel editing"
                        @click="emit('cancel')"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </td>
        </tr>
        <tr v-if="firstError">
            <td colspan="8" class="px-3 pb-2 text-xs text-destructive">
                {{ firstError }}
            </td>
        </tr>
    </tbody>
</template>
