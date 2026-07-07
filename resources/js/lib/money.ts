export type MoneyOptions = {
    currency?: string;
    locale?: string;
    maximumFractionDigits?: number;
};

export function formatMoney(
    amount: number,
    { currency = 'INR', locale = 'en-IN', maximumFractionDigits = 0 }: MoneyOptions = {},
): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        maximumFractionDigits,
    }).format(amount);
}

export function currencyLocale(currency: string): string {
    return currency === 'INR' ? 'en-IN' : 'en-US';
}
