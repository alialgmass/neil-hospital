import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

export interface SupplyRow {
    /** Stable row key (rows are merged/removed while editing). */
    uid: number;
    inventory_item_id: string;
    name: string;
    qty: number;
    unit_cost: number;
}

export type SupplyPayloadItem = {
    inventory_item_id: string;
    name: string;
    qty: number;
    unit_cost: number;
    total: number;
};

interface SearchMatch {
    id: string;
    name: string;
    sell_price?: number;
}

/**
 * Row editor state for the bulk "add supplies" forms (surgery / lasik).
 *
 * - Picking an item already present in another row increments that row's
 *   quantity instead of creating a duplicate (the backend merges the same way).
 * - A fresh empty row is appended after each pick so many items can be
 *   entered quickly and saved in one request.
 * - Server errors keyed `items.N.*` are mapped back to the submitted row.
 */
export function useSupplyRows() {
    let uidCounter = 0;

    function emptyRow(): SupplyRow {
        return { uid: ++uidCounter, inventory_item_id: '', name: '', qty: 1, unit_cost: 0 };
    }

    const rows = ref<SupplyRow[]>([emptyRow()]);
    const errors = ref<Record<string, string>>({});

    const pendingRows = computed(() => rows.value.filter((row) => row.inventory_item_id !== ''));
    const total = computed(() => pendingRows.value.reduce((sum, row) => sum + row.qty * row.unit_cost, 0));

    function addRow(): void {
        rows.value.push(emptyRow());
    }

    function removeRow(idx: number): void {
        rows.value.splice(idx, 1);

        if (rows.value.length === 0) {
            addRow();
        }
    }

    function reset(): void {
        rows.value = [emptyRow()];
        errors.value = {};
    }

    function rowError(uid: number): string | undefined {
        return errors.value[`row.${uid}`];
    }

    /** Errors not tied to a row (e.g. "items" required, surgery_id mismatch). */
    const generalErrors = computed(() =>
        Object.entries(errors.value)
            .filter(([key]) => !key.startsWith('row.'))
            .map(([, message]) => message),
    );

    function onSelected(idx: number, matched: SearchMatch): void {
        const row = rows.value[idx];
        const existingIdx = rows.value.findIndex((other, i) => i !== idx && other.inventory_item_id === matched.id);

        if (existingIdx !== -1) {
            rows.value[existingIdx].qty += row.qty > 0 ? row.qty : 1;
            rows.value.splice(idx, 1);
            toast.info(`«${matched.name}» مضاف بالفعل — تمت زيادة الكمية`);
        } else {
            row.inventory_item_id = matched.id;
            row.name = matched.name;
            row.unit_cost = Number(matched.sell_price ?? 0);
            delete errors.value[`row.${row.uid}`];
        }

        const last = rows.value[rows.value.length - 1];

        if (!last || last.inventory_item_id !== '') {
            addRow();
        }
    }

    /** Client-side checks; returns false (and fills `errors`) when a row is invalid. */
    function validate(): boolean {
        errors.value = {};

        for (const row of pendingRows.value) {
            if (!(row.qty > 0)) {
                errors.value[`row.${row.uid}`] = 'الكمية يجب أن تكون أكبر من صفر.';
            } else if (row.unit_cost < 0) {
                errors.value[`row.${row.uid}`] = 'السعر لا يمكن أن يكون سالباً.';
            }
        }

        return Object.keys(errors.value).length === 0;
    }

    function payload(submitted: SupplyRow[]): SupplyPayloadItem[] {
        return submitted.map((row) => ({
            inventory_item_id: row.inventory_item_id,
            name: row.name,
            qty: row.qty,
            unit_cost: row.unit_cost,
            total: row.qty * row.unit_cost,
        }));
    }

    /** Map Inertia validation errors back onto the rows that were submitted. */
    function applyServerErrors(serverErrors: Record<string, string>, submitted: SupplyRow[]): void {
        errors.value = {};

        for (const [key, message] of Object.entries(serverErrors)) {
            const match = key.match(/^items\.(\d+)\./);
            const row = match ? submitted[Number(match[1])] : undefined;

            errors.value[row ? `row.${row.uid}` : key] = message;
        }
    }

    return {
        rows,
        errors,
        pendingRows,
        total,
        generalErrors,
        addRow,
        removeRow,
        reset,
        rowError,
        onSelected,
        validate,
        payload,
        applyServerErrors,
    };
}
