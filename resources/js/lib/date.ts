/**
 * Formats a date using Western (en-US) digits, matching the numeric
 * formatting standard already used across the app (see money/count
 * formatting via toLocaleString('en-US', ...)).
 */
export function formatDate(date: string | Date, opts: Intl.DateTimeFormatOptions = {}): string {
    const d = date instanceof Date ? date : new Date(date);

    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        ...opts,
    });
}

/**
 * Trims an ISO/datetime string down to the YYYY-MM-DD form expected by
 * <input type="date">.
 */
export function toInputDate(date: string | Date | null | undefined): string {
    if (!date) {
        return '';
    }

    const value = date instanceof Date ? date.toISOString() : date;

    return value.slice(0, 10);
}
