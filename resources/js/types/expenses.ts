export type ExpenseEntryType = 'payment' | 'refund' | 'transfer';
export type ExpenseSplitType = 'equal' | 'exact' | 'percentage' | 'shares';

export type ExpenseParticipant = {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    user_id: number | null;
    archived: boolean;
    is_portal_user: boolean;
};

export type ExpenseAmountRow = {
    participant_id: string;
    amount: number;
};

export type ExpenseEntry = {
    id: string;
    type: ExpenseEntryType;
    type_label: string;
    category: string | null;
    category_label: string | null;
    title: string;
    entry_date: string | null;
    amount: number;
    payers: ExpenseAmountRow[];
    split_type: ExpenseSplitType;
    split_inputs: { participant_id: string; value?: number | null }[];
    splits: ExpenseAmountRow[];
    notes: string | null;
    created_by: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type ExpenseBalance = {
    participant_id: string;
    paid: number;
    share: number;
    sent: number;
    received: number;
    balance: number;
};

export type ExpenseSummary = {
    totals: { gross: number; refunded: number; net: number };
    budget: number | null;
    participants: ExpenseBalance[];
    categories: { category: string; label: string; net: number }[];
    settlements: { from: string; to: string; amount: number }[];
};

export type ExpenseActivity = {
    id: string;
    actor: string;
    summary: string;
    by_other: boolean;
    created_at: string | null;
};

export type ExpenseSheet = {
    id: string;
    status: 'open' | 'settled';
    settled_at: string | null;
    settled_by: string | null;
    participants: ExpenseParticipant[];
    entries: ExpenseEntry[];
    summary: ExpenseSummary;
    activities: ExpenseActivity[];
};

export type TripExpenses = {
    can_manage: boolean;
    suggested_participants: { name: string; email: string }[];
    sheet: ExpenseSheet | null;
};

export const EXPENSE_CATEGORIES: { value: string; label: string }[] = [
    { value: 'stay', label: 'Stay' },
    { value: 'transport', label: 'Transport' },
    { value: 'food', label: 'Food & drinks' },
    { value: 'activities', label: 'Activities' },
    { value: 'shopping', label: 'Shopping' },
    { value: 'fuel', label: 'Fuel & tolls' },
    { value: 'other', label: 'Miscellaneous' },
];
