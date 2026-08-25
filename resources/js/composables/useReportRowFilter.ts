import { computed, ref } from 'vue';

/**
 * Client-side search + per-row "exclude from this report" for report tables.
 * Exclusion only hides the row from this view/print — it never touches the
 * underlying record. Resets on reload (not persisted).
 *
 * `keyOf` is used only to make the internal identity readable for debugging —
 * it is combined with the row's position in `rows()` so two rows with the same
 * field values (e.g. two payments to the same doctor on the same day for the
 * same amount) never collide and get excluded together. Position is stable
 * because `rows()` must keep returning the same row objects by reference for
 * the lifetime of this filter (it may reorder/filter, but not clone).
 */
export function useReportRowFilter<T>(
    rows: () => T[],
    searchFields: (keyof T)[],
    keyOf: (row: T) => string,
) {
    const search = ref('');
    const excluded = ref<Set<string>>(new Set());

    function identityOf(row: T, index: number): string {
        return `${index}:${keyOf(row)}`;
    }

    const visibleRows = computed(() => {
        const q = search.value.trim();

        return rows()
            .map((r, i) => [r, i] as const)
            .filter(([r]) => !q || searchFields.some((f) => String(r[f] ?? '').includes(q)))
            .filter(([r, i]) => !excluded.value.has(identityOf(r, i)))
            .map(([r]) => r);
    });

    const excludedCount = computed(() => excluded.value.size);

    function exclude(row: T) {
        const index = rows().indexOf(row);
        excluded.value = new Set(excluded.value).add(identityOf(row, index));
    }

    function restoreAll() {
        excluded.value = new Set();
    }

    return { search, visibleRows, excludedCount, exclude, restoreAll };
}
