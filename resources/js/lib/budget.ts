import { currencyLocale, formatMoney } from '@/lib/money';

/**
 * Normalize AI budget object into display-friendly lines.
 * Stored shape: { currency, estimated_total, breakdown: Record<string, number> }
 */
export type BudgetLine = {
    label: string;
    amount: string;
};

const RESERVED_KEYS = new Set(['estimated_total', 'total', 'breakdown', 'currency']);

export function formatBudgetLabel(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function toAmount(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '' && ! Number.isNaN(Number(value))) {
        return Number(value);
    }

    return null;
}

export function formatBudgetAmount(value: unknown, currency = 'INR'): string {
    const amount = toAmount(value);

    if (amount === null) {
        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim();
        }

        return '—';
    }

    return formatMoney(amount, { currency, locale: currencyLocale(currency) });
}

function fromAssociativeBreakdown(
    data: Record<string, unknown>,
): Array<{ label: string; amount: number }> {
    return Object.entries(data)
        .filter(([key]) => ! RESERVED_KEYS.has(key))
        .map(([key, value]) => ({
            label: formatBudgetLabel(key),
            amount: toAmount(value),
        }))
        .filter((line): line is { label: string; amount: number } => line.amount !== null);
}

function fromListBreakdown(items: unknown[]): Array<{ label: string; amount: number }> {
    const lines: Array<{ label: string; amount: number }> = [];

    for (const item of items) {
        if (! item || typeof item !== 'object' || Array.isArray(item)) {
            continue;
        }

        const record = item as Record<string, unknown>;
        const key = record.category ?? record.label ?? record.name;
        const amount = toAmount(record.amount ?? record.value);

        if (typeof key === 'string' && key.trim() !== '' && amount !== null) {
            lines.push({
                label: formatBudgetLabel(key),
                amount,
            });
        }
    }

    return lines;
}

export function normalizeBudgetBreakdown(
    breakdown: Record<string, unknown> | undefined,
    defaultCurrency = 'INR',
): { estimatedTotal: string | null; lines: BudgetLine[]; currency: string; hasLineItems: boolean } {
    if (! breakdown || Object.keys(breakdown).length === 0) {
        return { estimatedTotal: null, lines: [], currency: defaultCurrency, hasLineItems: false };
    }

    const currency =
        typeof breakdown.currency === 'string' && breakdown.currency.trim() !== ''
            ? breakdown.currency.trim().toUpperCase()
            : defaultCurrency;

    const nested = breakdown.breakdown;
    let rawLines: Array<{ label: string; amount: number }> = [];

    if (Array.isArray(nested)) {
        rawLines = fromListBreakdown(nested);
    } else if (nested && typeof nested === 'object') {
        rawLines = fromAssociativeBreakdown(nested as Record<string, unknown>);
    } else {
        rawLines = fromAssociativeBreakdown(breakdown);
    }

    const lines = rawLines.map((line) => ({
        label: line.label,
        amount: formatMoney(line.amount, { currency, locale: currencyLocale(currency) }),
    }));

    const explicitTotal = toAmount(breakdown.estimated_total ?? breakdown.total);
    const summedTotal = rawLines.reduce((sum, line) => sum + line.amount, 0);
    const totalAmount = explicitTotal ?? (summedTotal > 0 ? summedTotal : null);

    return {
        estimatedTotal: totalAmount !== null
            ? formatMoney(totalAmount, { currency, locale: currencyLocale(currency) })
            : null,
        lines,
        currency,
        hasLineItems: lines.length > 0,
    };
}
