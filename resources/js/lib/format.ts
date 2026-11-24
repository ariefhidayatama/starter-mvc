/**
 * Date formatting helpers for display.
 */

const LOCALE = 'en-US';

const dateFormatter = new Intl.DateTimeFormat(LOCALE, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const relativeFormatter = new Intl.RelativeTimeFormat(LOCALE, {
    numeric: 'auto',
});

const currencyFormatter = new Intl.NumberFormat(LOCALE, {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 2,
    minimumFractionDigits: 0,
});

/** "85000.00" → "IDR 85,000" */
export function formatCurrency(amount: string | number): string {
    return currencyFormatter.format(Number(amount));
}

/** "Nov 10, 2026" */
export function formatDate(iso: string | null | undefined): string {
    return iso ? dateFormatter.format(new Date(iso)) : '-';
}

/** "3 days ago", "yesterday", "just now" */
export function formatRelative(iso: string | null | undefined): string {
    if (!iso) {
        return '-';
    }

    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormatter.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}
