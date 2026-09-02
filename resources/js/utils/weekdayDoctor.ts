/**
 * Display-only fallback: when a booking has no doctor recorded, show the
 * doctor who normally covers that weekday, per the clinic's fixed roster.
 * This never touches `doctor_id` — purely a UI label for rows missing one.
 */
const WEEKDAY_DOCTOR: Record<number, string> = {
    6: 'د/ رضوى سامي', // السبت
    1: 'د/ أسامة وجيه', // الاثنين
    4: 'د/ أسامة وجيه', // الخميس
};

export function weekdayDoctorFallback(dateStr: string): string | null {
    const [y, m, d] = dateStr.split('-').map(Number);
    if (!y || !m || !d) {
        return null;
    }

    return WEEKDAY_DOCTOR[new Date(y, m - 1, d).getDay()] ?? null;
}
