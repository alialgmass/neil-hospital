export interface Company {
    id: string;
    name: string;
    code?: string;
    phone?: string;
    coverage_pct: number;
    disc_pct: number;
    contact_person?: string;
    email?: string;
    status: 'active' | 'inactive';
    contract_no?: string;
}

export interface Claim {
    id: number;
    insurance_company_id: string;
    booking_id?: string;
    service_id?: string;
    patient_name: string;
    file_no?: string;
    service_name: string;
    invoice_amount: number;
    discount: number;
    patient_share: number;
    insurance_share: number;
    approved_amount: number;
    paid_amount: number;
    status: 'draft' | 'submitted' | 'approved' | 'rejected' | 'paid';
    service_date: string;
    claim_date: string;
    submission_date?: string;
    approval_date?: string;
    payment_date?: string;
    claim_reference?: string;
    rejection_reason?: string;
    notes?: string;
    company?: { id: string; name: string };
    service?: { id: string; name: string };
}

export const claimStatusLabels: Record<string, string> = {
    draft: 'غير مسددة',
    submitted: 'مُرسلة',
    approved: 'معتمدة',
    rejected: 'مرفوضة',
    paid: 'مسددة',
};

export const claimStatusVariants: Record<Claim['status'], string> = {
    draft: 'draft',
    submitted: 'info',
    approved: 'success',
    rejected: 'danger',
    paid: 'paid',
};

export type PriceListType = 'cash' | 'insurance' | 'vip' | 'special';

export interface PriceListCompany {
    id: string;
    name: string;
    coverage_pct: number;
}

export interface PriceListService {
    id: string;
    name: string;
    dept: string;
    price: number;
    ins_price: number;
}

export interface PriceListItem {
    id: number;
    service_id: string;
    price: number;
    service?: { id: string; name: string; dept: string };
}

export interface PriceList {
    id: string;
    name: string;
    type: PriceListType;
    ins_company_id?: string | null;
    ins_coverage?: number | null;
    discount_pct: number;
    notes?: string | null;
    is_active: boolean;
    company?: PriceListCompany;
    items: PriceListItem[];
}
