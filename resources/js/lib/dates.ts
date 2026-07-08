const DISPLAY_PATTERN = /^(\d{2})\/(\d{2})\/(\d{4})$/;

/**
 * Format an ISO date (YYYY-MM-DD) for display as DD/MM/YYYY.
 */
export function formatDisplayDate(
    iso: string | null | undefined,
    options?: { weekday?: boolean },
): string {
    if (!iso) {
        return '—';
    }

    const [year, month, day] = iso.split('-').map(Number);

    if (!year || !month || !day) {
        return '—';
    }

    if (options?.weekday) {
        return new Date(year, month - 1, day).toLocaleDateString('en-GB', {
            weekday: 'short',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    }

    return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year}`;
}

/**
 * Format a trip date range using DD/MM/YYYY.
 */
export function formatDisplayDateRange(
    startDate: string | null | undefined,
    endDate: string | null | undefined,
): string {
    if (!startDate && !endDate) {
        return 'Dates not set';
    }

    const start = startDate ? formatDisplayDate(startDate) : '?';
    const end = endDate ? formatDisplayDate(endDate) : '?';

    return `${start} – ${end}`;
}

/**
 * Parse DD/MM/YYYY into an ISO date (YYYY-MM-DD).
 */
export function parseDisplayDate(value: string): string | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const match = trimmed.match(DISPLAY_PATTERN);

    if (!match) {
        return null;
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);

    if (month < 1 || month > 12 || day < 1 || day > 31) {
        return null;
    }

    const iso = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const parsed = new Date(year, month - 1, day);

    if (
        parsed.getFullYear() !== year ||
        parsed.getMonth() + 1 !== month ||
        parsed.getDate() !== day
    ) {
        return null;
    }

    return iso;
}

export function formatWeekdayShort(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const parsed = parseIsoDate(iso);

    if (!parsed) {
        return '';
    }

    return new Date(
        parsed.year,
        parsed.month - 1,
        parsed.day,
    ).toLocaleDateString('en-GB', {
        weekday: 'short',
    });
}

export function isoToday(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

export function isoToDisplay(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    return formatDisplayDate(iso) === '—' ? '' : formatDisplayDate(iso);
}

export function parseIsoDate(
    iso: string,
): { year: number; month: number; day: number } | null {
    const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return null;
    }

    return {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
    };
}

export function buildIsoDate(year: number, month: number, day: number): string {
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function compareIsoDates(left: string, right: string): number {
    return left.localeCompare(right);
}

export function daysInMonth(year: number, month: number): number {
    return new Date(year, month, 0).getDate();
}

export function monthLabel(year: number, month: number): string {
    return new Date(year, month - 1, 1).toLocaleDateString('en-GB', {
        month: 'long',
        year: 'numeric',
    });
}
