<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    Download,
    FileText,
    Lock,
    LockOpen,
    Mail,
    Pencil,
    Plus,
    Receipt,
    Trash2,
    Undo2,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripExpenseController from '@/actions/App/Http/Controllers/TripExpenseController';
import TripHubExpenseEmailDialog from '@/components/trip-hub/TripHubExpenseEmailDialog.vue';
import TripHubExpenseEntryDialog from '@/components/trip-hub/TripHubExpenseEntryDialog.vue';
import type { ExpenseDialogPrefill } from '@/components/trip-hub/TripHubExpenseEntryDialog.vue';
import TripHubExpenseParticipantsDialog from '@/components/trip-hub/TripHubExpenseParticipantsDialog.vue';
import TripHubExpenseReopenDialog from '@/components/trip-hub/TripHubExpenseReopenDialog.vue';
import TripHubExpenseRow from '@/components/trip-hub/TripHubExpenseRow.vue';
import TripHubExpenseSettleDialog from '@/components/trip-hub/TripHubExpenseSettleDialog.vue';
import TripHubExpenseSetupDialog from '@/components/trip-hub/TripHubExpenseSetupDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useExpenseRequest } from '@/composables/useExpenseRequest';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type {
    ExpenseEntry,
    ExpenseEntryType,
    TripExpenses,
} from '@/types/expenses';

const props = defineProps<{
    tripId: string;
    expenses?: TripExpenses | null;
}>();

const page = usePage();
const currency = computed(
    () => (page.props.currency as { code?: string } | undefined)?.code ?? 'INR',
);
const locale = computed(
    () =>
        (page.props.currency as { locale?: string } | undefined)?.locale ??
        'en-IN',
);

function money(amount: number): string {
    return formatMoney(amount, {
        currency: currency.value,
        locale: locale.value,
        maximumFractionDigits: 2,
    });
}

const sheet = computed(() => props.expenses?.sheet ?? null);
const isLoading = computed(() => props.expenses === undefined);
const canManage = computed(() => props.expenses?.can_manage ?? false);
const isSettled = computed(() => sheet.value?.status === 'settled');
const canEdit = computed(() => canManage.value && !isSettled.value);

const participants = computed(() => sheet.value?.participants ?? []);
const activeParticipants = computed(() =>
    participants.value.filter((participant) => !participant.archived),
);

function personName(participantId: string): string {
    return (
        participants.value.find(
            (participant) => participant.id === participantId,
        )?.name ?? 'Unknown'
    );
}

function listPeople(
    rows: { participant_id: string; amount: number }[],
): string {
    return rows
        .map((row) => `${personName(row.participant_id)} ${money(row.amount)}`)
        .join(', ');
}

type DayGroup = { date: string | null; total: number; entries: ExpenseEntry[] };

const dayGroups = computed<DayGroup[]>(() => {
    const groups = new Map<string, DayGroup>();

    for (const entry of sheet.value?.entries ?? []) {
        const key = entry.entry_date ?? '';
        const group = groups.get(key) ?? {
            date: entry.entry_date,
            total: 0,
            entries: [],
        };

        group.entries.push(entry);
        group.total +=
            entry.type === 'payment'
                ? entry.amount
                : entry.type === 'refund'
                  ? -entry.amount
                  : 0;
        groups.set(key, group);
    }

    return [...groups.values()];
});

function isSimple(entry: ExpenseEntry): boolean {
    if (entry.type === 'transfer') {
        return true;
    }

    return (
        entry.payers.length === 1 &&
        entry.split_type === 'equal' &&
        (entry.splits.length === 1 ||
            entry.splits.length === activeParticipants.value.length)
    );
}

function splitText(entry: ExpenseEntry): string {
    if (entry.type === 'transfer') {
        return `→ ${personName(entry.splits[0]?.participant_id ?? '')}`;
    }

    if (
        entry.split_type === 'equal' &&
        entry.splits.length === activeParticipants.value.length
    ) {
        return 'Everyone, equally';
    }

    const names = entry.splits
        .map((row) => personName(row.participant_id))
        .join(', ');

    return entry.split_type === 'equal' ? names : `${names} (custom)`;
}

const budgetLeft = computed(() => {
    const budget = sheet.value?.summary.budget;

    return budget ? budget - (sheet.value?.summary.totals.net ?? 0) : null;
});

const outstanding = computed(() =>
    (sheet.value?.summary.settlements ?? []).map((row) => ({
        text: `${personName(row.from)} owes ${personName(row.to)} ${money(row.amount)}`,
    })),
);

function formatDate(date: string | null): string {
    if (!date) {
        return '';
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatDateTime(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '';
}

const setupOpen = ref(false);
const participantsOpen = ref(false);
const entryOpen = ref(false);
const dialogEntry = ref<ExpenseEntry | null>(null);
const dialogPrefill = ref<ExpenseDialogPrefill | null>(null);
const editingId = ref<string | null>(null);
const settleOpen = ref(false);
const reopenOpen = ref(false);
const emailOpen = ref(false);
const downloadFormat = ref<'csv' | 'pdf' | null>(null);
const lastDownloadFormat = ref<'csv' | 'pdf'>('pdf');

watch(downloadFormat, (format) => {
    if (format !== null) {
        lastDownloadFormat.value = format;
    }
});

/** Kept after the dialog closes so the click that closes it still follows the link. */
const downloadUrl = computed(() =>
    lastDownloadFormat.value === 'csv'
        ? TripExpenseController.exportCsv.url({ trip: props.tripId })
        : TripExpenseController.exportPdf.url({ trip: props.tripId }),
);

const entryToDelete = ref<ExpenseEntry | null>(null);
const deleteRequest = useExpenseRequest();

function openDialog(
    entry: ExpenseEntry | null,
    prefill: ExpenseDialogPrefill | null = null,
): void {
    dialogEntry.value = entry;
    dialogPrefill.value = prefill;
    entryOpen.value = true;
}

function startEdit(entry: ExpenseEntry): void {
    if (isSimple(entry)) {
        editingId.value = entry.id;

        return;
    }

    openDialog(entry);
}

function confirmDelete(): void {
    if (!entryToDelete.value) {
        return;
    }

    deleteRequest.send(
        'delete',
        TripExpenseController.destroyEntry.url({
            trip: props.tripId,
            entry: entryToDelete.value.id,
        }),
        {},
        () => {
            entryToDelete.value = null;
        },
    );
}

function entryIcon(type: ExpenseEntryType) {
    return type === 'refund'
        ? Undo2
        : type === 'transfer'
          ? ArrowRightLeft
          : Receipt;
}
</script>

<template>
    <Card class="card-vibrant overflow-hidden">
        <div
            class="h-1.5 bg-gradient-to-r from-emerald-400 via-teal-500 to-cyan-500"
        />
        <CardHeader class="pb-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <CardTitle class="flex items-center gap-2 text-lg font-bold">
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
                    >
                        <Wallet class="size-4" />
                    </span>
                    Expenses
                    <Badge
                        v-if="sheet"
                        :variant="isSettled ? 'secondary' : 'outline'"
                    >
                        <Lock v-if="isSettled" />
                        {{ isSettled ? 'Settled' : 'Open' }}
                    </Badge>
                </CardTitle>

                <div v-if="sheet" class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="downloadFormat = 'csv'"
                    >
                        <Download class="size-4" />
                        CSV
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="downloadFormat = 'pdf'"
                    >
                        <FileText class="size-4" />
                        PDF
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="emailOpen = true"
                    >
                        <Mail class="size-4" />
                        Email
                    </Button>
                    <Button
                        v-if="canManage && !isSettled"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="settleOpen = true"
                    >
                        <Lock class="size-4" />
                        Settle
                    </Button>
                    <Button
                        v-if="canManage && isSettled"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="reopenOpen = true"
                    >
                        <LockOpen class="size-4" />
                        Reopen
                    </Button>
                </div>
            </div>
            <p class="text-sm text-muted-foreground">
                Track who paid for what, and who owes whom.
            </p>
        </CardHeader>

        <CardContent class="space-y-6">
            <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-16 w-full" />
                <Skeleton class="h-32 w-full" />
            </div>

            <div
                v-else-if="!sheet"
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border/70 px-6 py-10 text-center"
            >
                <p class="max-w-md text-sm text-muted-foreground">
                    <template v-if="canManage">
                        Start an expense sheet to log day-to-day costs, split
                        them between friends (even ones without an account) and
                        see who owes whom.
                    </template>
                    <template v-else>
                        There is no expense sheet for this trip yet.
                    </template>
                </p>
                <Button
                    v-if="canManage"
                    type="button"
                    @click="setupOpen = true"
                >
                    <Plus class="size-4" />
                    Create expense sheet
                </Button>
            </div>

            <template v-else>
                <div
                    v-if="isSettled"
                    class="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm"
                >
                    <Lock class="size-4 shrink-0 text-emerald-600" />
                    Settled by {{ sheet.settled_by }} on
                    {{ formatDateTime(sheet.settled_at) }}. This sheet is
                    read-only until it is reopened.
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div
                        class="rounded-xl border border-border/60 bg-muted/20 p-3"
                    >
                        <p class="text-xs text-muted-foreground">Total spend</p>
                        <p class="text-xl font-bold">
                            {{ money(sheet.summary.totals.net) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ money(sheet.summary.totals.gross) }} paid ·
                            {{ money(sheet.summary.totals.refunded) }} refunded
                        </p>
                    </div>
                    <div
                        v-if="sheet.summary.budget"
                        class="rounded-xl border border-border/60 bg-muted/20 p-3"
                    >
                        <p class="text-xs text-muted-foreground">Budget</p>
                        <p class="text-xl font-bold">
                            {{ money(sheet.summary.budget) }}
                        </p>
                        <p
                            :class="
                                cn(
                                    'text-xs',
                                    (budgetLeft ?? 0) < 0
                                        ? 'text-destructive'
                                        : 'text-muted-foreground',
                                )
                            "
                        >
                            {{
                                (budgetLeft ?? 0) < 0
                                    ? `${money(Math.abs(budgetLeft ?? 0))} over budget`
                                    : `${money(budgetLeft ?? 0)} left`
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border/60 bg-muted/20 p-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            Top categories
                        </p>
                        <p
                            v-for="category in sheet.summary.categories.slice(
                                0,
                                3,
                            )"
                            :key="category.category"
                            class="flex justify-between text-sm"
                        >
                            <span>{{ category.label }}</span>
                            <span class="font-medium">{{
                                money(category.net)
                            }}</span>
                        </p>
                        <p
                            v-if="sheet.summary.categories.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Nothing yet
                        </p>
                    </div>
                </div>

                <section class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">
                            Who paid and who owes
                        </h3>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="participantsOpen = true"
                        >
                            <Users class="size-4" />
                            Participants ({{ activeParticipants.length }})
                        </Button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left text-xs">
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        Person
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Paid
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Share
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Balance
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="row in sheet.summary.participants"
                                    :key="row.participant_id"
                                >
                                    <td class="px-3 py-2">
                                        {{ personName(row.participant_id) }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{ money(row.paid) }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{ money(row.share) }}
                                    </td>
                                    <td
                                        :class="
                                            cn(
                                                'px-3 py-2 text-right font-semibold',
                                                row.balance > 0
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : row.balance < 0
                                                      ? 'text-destructive'
                                                      : 'text-muted-foreground',
                                            )
                                        "
                                    >
                                        {{ money(row.balance) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Positive: is owed money. Negative: owes money. Personal
                        payments between people are included.
                    </p>
                    <ul
                        v-if="sheet.summary.settlements.length > 0"
                        class="space-y-1 rounded-lg bg-muted/30 p-3 text-sm"
                    >
                        <li
                            class="text-xs font-semibold text-muted-foreground uppercase"
                        >
                            To settle up
                        </li>
                        <li
                            v-for="row in sheet.summary.settlements"
                            :key="`${row.from}-${row.to}`"
                            class="flex items-center gap-2"
                        >
                            <span class="font-medium">{{
                                personName(row.from)
                            }}</span>
                            pays
                            <span class="font-medium">{{
                                personName(row.to)
                            }}</span>
                            <span class="ml-auto font-semibold">{{
                                money(row.amount)
                            }}</span>
                        </li>
                    </ul>
                </section>

                <section class="space-y-2">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h3 class="text-sm font-semibold">Expenses</h3>
                        <p v-if="canEdit" class="text-xs text-muted-foreground">
                            Fill the top row and press Enter to add. Click the
                            pencil to change a row.
                        </p>
                    </div>

                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full min-w-[56rem] text-sm">
                            <thead class="bg-muted/40 text-left text-xs">
                                <tr>
                                    <th class="w-36 px-3 py-2 font-medium">
                                        Type
                                    </th>
                                    <th class="w-36 px-3 py-2 font-medium">
                                        Date
                                    </th>
                                    <th class="px-3 py-2 font-medium">What</th>
                                    <th class="w-36 px-3 py-2 font-medium">
                                        Category
                                    </th>
                                    <th
                                        class="w-28 px-3 py-2 text-right font-medium"
                                    >
                                        Amount
                                    </th>
                                    <th class="w-36 px-3 py-2 font-medium">
                                        Paid by
                                    </th>
                                    <th class="w-44 px-3 py-2 font-medium">
                                        Split between
                                    </th>
                                    <th class="w-20 px-3 py-2" />
                                </tr>
                            </thead>

                            <TripHubExpenseRow
                                v-if="canEdit && activeParticipants.length > 0"
                                :trip-id="tripId"
                                :participants="participants"
                                @advanced="openDialog(null, $event)"
                            />

                            <tbody v-if="sheet.entries.length === 0">
                                <tr>
                                    <td
                                        colspan="8"
                                        class="px-3 py-6 text-center text-sm text-muted-foreground"
                                    >
                                        Nothing recorded yet.
                                    </td>
                                </tr>
                            </tbody>

                            <template
                                v-for="group in dayGroups"
                                :key="group.date ?? 'undated'"
                            >
                                <tbody>
                                    <tr class="bg-muted/30">
                                        <td
                                            colspan="4"
                                            class="px-3 py-1.5 text-xs font-semibold"
                                        >
                                            {{ formatDate(group.date) }}
                                        </td>
                                        <td
                                            class="px-3 py-1.5 text-right text-xs font-semibold"
                                        >
                                            {{ money(group.total) }}
                                        </td>
                                        <td colspan="3" />
                                    </tr>
                                </tbody>

                                <template
                                    v-for="entry in group.entries"
                                    :key="entry.id"
                                >
                                    <TripHubExpenseRow
                                        v-if="editingId === entry.id"
                                        :trip-id="tripId"
                                        :participants="participants"
                                        :entry="entry"
                                        @done="editingId = null"
                                        @cancel="editingId = null"
                                        @advanced="
                                            (prefill) => {
                                                editingId = null;
                                                openDialog(entry, prefill);
                                            }
                                        "
                                    />
                                    <tbody v-else>
                                        <tr
                                            class="border-t border-border/60 align-top"
                                        >
                                            <td
                                                class="px-3 py-2 text-xs text-muted-foreground"
                                            >
                                                <span
                                                    class="inline-flex items-center gap-1.5"
                                                >
                                                    <component
                                                        :is="
                                                            entryIcon(
                                                                entry.type,
                                                            )
                                                        "
                                                        class="size-3.5"
                                                    />
                                                    {{ entry.type_label }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-3 py-2 text-muted-foreground"
                                            >
                                                {{
                                                    formatDate(entry.entry_date)
                                                }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <p class="font-medium">
                                                    {{
                                                        entry.title ||
                                                        (entry.type ===
                                                        'transfer'
                                                            ? 'Personal payment'
                                                            : '—')
                                                    }}
                                                </p>
                                                <p
                                                    v-if="entry.notes"
                                                    class="text-xs text-muted-foreground italic"
                                                >
                                                    {{ entry.notes }}
                                                </p>
                                                <p
                                                    class="text-[11px] text-muted-foreground/70"
                                                >
                                                    Added by
                                                    {{ entry.created_by }}
                                                </p>
                                            </td>
                                            <td class="px-3 py-2">
                                                <Badge
                                                    v-if="entry.category_label"
                                                    variant="outline"
                                                    class="font-normal"
                                                >
                                                    {{ entry.category_label }}
                                                </Badge>
                                            </td>
                                            <td
                                                :class="
                                                    cn(
                                                        'px-3 py-2 text-right font-semibold',
                                                        entry.type ===
                                                            'refund' &&
                                                            'text-emerald-600 dark:text-emerald-400',
                                                        entry.type ===
                                                            'transfer' &&
                                                            'text-muted-foreground',
                                                    )
                                                "
                                            >
                                                {{
                                                    entry.type === 'refund'
                                                        ? '−'
                                                        : ''
                                                }}{{ money(entry.amount) }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <template
                                                    v-if="
                                                        entry.payers.length ===
                                                        1
                                                    "
                                                >
                                                    {{
                                                        personName(
                                                            entry.payers[0]
                                                                .participant_id,
                                                        )
                                                    }}
                                                </template>
                                                <template v-else>
                                                    {{
                                                        listPeople(entry.payers)
                                                    }}
                                                </template>
                                            </td>
                                            <td
                                                class="px-3 py-2 text-muted-foreground"
                                            >
                                                {{ splitText(entry) }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <div
                                                    v-if="canEdit"
                                                    class="flex justify-end gap-1"
                                                >
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label="Edit row"
                                                        @click="
                                                            startEdit(entry)
                                                        "
                                                    >
                                                        <Pencil
                                                            class="size-4"
                                                        />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label="Delete row"
                                                        @click="
                                                            entryToDelete =
                                                                entry
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-4"
                                                        />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </template>
                            </template>

                            <tfoot v-if="sheet.entries.length > 0">
                                <tr
                                    class="border-t-2 bg-muted/40 font-semibold"
                                >
                                    <td colspan="4" class="px-3 py-2">
                                        Total spend
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{ money(sheet.summary.totals.net) }}
                                    </td>
                                    <td colspan="3" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section class="space-y-2">
                    <h3 class="text-sm font-semibold">Activity log</h3>
                    <p
                        v-if="sheet.activities.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No activity yet.
                    </p>
                    <ul
                        class="max-h-72 space-y-1.5 overflow-y-auto pr-1 text-sm"
                    >
                        <li
                            v-for="activity in sheet.activities"
                            :key="activity.id"
                            :class="
                                cn(
                                    'rounded-md border border-transparent px-2 py-1.5',
                                    activity.by_other &&
                                        'border-amber-500/40 bg-amber-500/10',
                                )
                            "
                        >
                            <span class="font-medium">{{
                                activity.actor
                            }}</span>
                            {{ activity.summary }}
                            <span
                                v-if="activity.by_other"
                                class="text-xs font-medium text-amber-700 dark:text-amber-400"
                            >
                                (entry created by someone else)
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{ formatDateTime(activity.created_at) }}
                            </span>
                        </li>
                    </ul>
                </section>
            </template>
        </CardContent>

        <TripHubExpenseSetupDialog
            v-model:open="setupOpen"
            :trip-id="tripId"
            :suggested="expenses?.suggested_participants ?? []"
        />

        <template v-if="sheet">
            <TripHubExpenseParticipantsDialog
                v-model:open="participantsOpen"
                :trip-id="tripId"
                :participants="participants"
                :suggested="expenses?.suggested_participants ?? []"
                :editable="canEdit"
            />
            <TripHubExpenseEntryDialog
                v-model:open="entryOpen"
                :trip-id="tripId"
                :participants="participants"
                :entry="dialogEntry"
                :prefill="dialogPrefill"
            />
            <TripHubExpenseSettleDialog
                v-model:open="settleOpen"
                :trip-id="tripId"
                :outstanding="outstanding"
            />
            <TripHubExpenseReopenDialog
                v-model:open="reopenOpen"
                :trip-id="tripId"
                :participant-count="activeParticipants.length"
            />
            <TripHubExpenseEmailDialog
                v-model:open="emailOpen"
                :trip-id="tripId"
                :participants="participants"
            />

            <Dialog
                :open="downloadFormat !== null"
                @update:open="(value) => !value && (downloadFormat = null)"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Download the {{ downloadFormat?.toUpperCase() }}
                            report?
                        </DialogTitle>
                        <DialogDescription>
                            The report lists every participant's name, what they
                            paid and owe, and the full activity log. Keep the
                            file private and only share it with people who
                            should see these amounts.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="downloadFormat = null"
                        >
                            Cancel
                        </Button>
                        <Button as-child>
                            <a
                                :href="downloadUrl"
                                @click="downloadFormat = null"
                            >
                                <Download class="size-4" />
                                Download
                            </a>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="entryToDelete !== null"
                @update:open="(value) => !value && (entryToDelete = null)"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete this entry?</DialogTitle>
                        <DialogDescription>
                            "{{ entryToDelete?.title }}" will be removed from
                            the totals. The deletion is recorded in the activity
                            log.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="entryToDelete = null"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="deleteRequest.processing.value"
                            @click="confirmDelete"
                        >
                            <Spinner
                                v-if="deleteRequest.processing.value"
                                class="size-4"
                            />
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </template>
    </Card>
</template>
