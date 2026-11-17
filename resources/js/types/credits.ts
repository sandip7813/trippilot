export type CreditServiceKey =
    | 'itinerary_generation'
    | 'itinerary_regeneration'
    | 'trip_chat_message'
    | 'assistant_message'
    | 'road_break_suggestions';

/**
 * Shared on every page for users who spend credits; null for guests, admins,
 * and while credits are switched off.
 */
export type SharedCredits = {
    balance: number;
    costs: Record<CreditServiceKey, number>;
} | null;

export type CreditServiceCost = {
    service: CreditServiceKey;
    label: string;
    credits: number;
};

export type CreditTransaction = {
    id: number;
    type:
        | 'signup_bonus'
        | 'admin_grant'
        | 'purchase'
        | 'purchase_reversal'
        | 'spend'
        | 'refund';
    label: string;
    type_label: string;
    amount: number;
    balance_after: number;
    trip_title: string | null;
    note: string | null;
    created_at: string | null;
};

export type CreditPackOption = {
    id: number;
    name: string;
    price: number;
    price_paise: number;
    credits: number;
    is_featured: boolean;
};
