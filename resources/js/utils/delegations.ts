export interface DelegationLine {
    doctor_id: string;
    role: 'delegate' | 'anesthesia';
    service_id: string | null;
    service_name: string;
    amount: number;
}

const ROLE_LABEL: Record<DelegationLine['role'], string> = {
    delegate: 'الطبيب المفوَّض',
    anesthesia: 'طبيب التخدير',
};

/**
 * Delegation/anesthesia lines are only valid with a positive amount — the
 * server rejects `min:0.01`. Returns the Arabic message for the first
 * offending line, or null when every picked doctor has an amount. Without
 * this guard a doctor with no configured fee is sent as amount 0 and the
 * whole request 422s on an error the form never renders.
 */
export function delegationAmountError(lines: DelegationLine[]): string | null {
    const invalid = lines.find((l) => !l.doctor_id || !(Number(l.amount) > 0));

    return invalid
        ? `يرجى إدخال مبلغ ${ROLE_LABEL[invalid.role]} قبل الحفظ.`
        : null;
}
