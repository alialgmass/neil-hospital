/**
 * Shared types and field definitions for the ophthalmic medical examination
 * (form page + print report). Field keys mirror MedicalExaminationEye::FIELDS
 * and MedicalExaminationData::ATTRIBUTE_FIELDS on the backend.
 */

export type Eye = 'OD' | 'OS';

export const EYES: Eye[] = ['OD', 'OS'];

export const EYE_LABELS: Record<Eye, string> = {
    OD: 'العين اليمنى — Right',
    OS: 'العين اليسرى — Left',
};

export interface Option {
    value: string;
    label: string;
}

export type EyeFieldKey =
    | 'ucva' | 'cva' | 'near_vision'
    | 'sphere' | 'cylinder' | 'axis' | 'add_power' | 'bcva'
    | 'eyelids' | 'conjunctiva' | 'sclera' | 'cornea' | 'anterior_chamber' | 'iris' | 'pupil' | 'lens'
    | 'iop'
    | 'optic_disc' | 'cd_ratio' | 'macula' | 'retina' | 'vessels' | 'vitreous';

export type EyeFindings = Record<EyeFieldKey, string | number | null>;

export interface EyeRow {
    key: EyeFieldKey;
    label: string;
    type?: 'text' | 'number';
    step?: number;
    min?: number;
    max?: number;
    placeholder?: string;
    suggestions?: string[];
    /** Value used by the "normal" quick-fill button. */
    normal?: string;
    /** Signed refraction values are shown with an explicit + sign. */
    signed?: boolean;
}

const ACUITY = ['6/5', '6/6', '6/9', '6/12', '6/18', '6/24', '6/36', '6/60', '5/60', '3/60', '1/60', 'CF', 'HM', 'PL', 'NPL'];
const NEAR = ['N5', 'N6', 'N8', 'N10', 'N12', 'N18', 'N24', 'N36'];

export const VISUAL_ACUITY_ROWS: EyeRow[] = [
    { key: 'ucva', label: 'VA without correction (UCVA)', placeholder: '6/18', suggestions: ACUITY },
    { key: 'cva', label: 'VA with correction', placeholder: '6/6', suggestions: ACUITY },
    { key: 'near_vision', label: 'Near Vision', placeholder: 'N6', suggestions: NEAR },
];

export const REFRACTION_ROWS: EyeRow[] = [
    { key: 'sphere', label: 'Sphere (D)', type: 'number', step: 0.25, min: -30, max: 30, placeholder: '0.00', signed: true },
    { key: 'cylinder', label: 'Cylinder (D)', type: 'number', step: 0.25, min: -15, max: 15, placeholder: '0.00', signed: true },
    { key: 'axis', label: 'Axis (°)', type: 'number', step: 1, min: 0, max: 180, placeholder: '0–180' },
    { key: 'add_power', label: 'Add (D)', type: 'number', step: 0.25, min: 0, max: 4, placeholder: '+0.00', signed: true },
    { key: 'bcva', label: 'Best Corrected VA (BCVA)', placeholder: '6/6', suggestions: ACUITY },
];

export const ANTERIOR_SEGMENT_ROWS: EyeRow[] = [
    { key: 'eyelids', label: 'Eyelids', normal: 'Normal', suggestions: ['Normal', 'Blepharitis', 'Ptosis', 'Chalazion', 'Stye', 'Entropion', 'Ectropion', 'Lid mass'] },
    { key: 'conjunctiva', label: 'Conjunctiva', normal: 'Quiet', suggestions: ['Quiet', 'Injected', 'Papillae', 'Follicles', 'Pterygium', 'Pinguecula', 'Subconjunctival hemorrhage', 'Chemosis'] },
    { key: 'sclera', label: 'Sclera', normal: 'White', suggestions: ['White', 'Injected', 'Thinning', 'Nodule'] },
    { key: 'cornea', label: 'Cornea', normal: 'Clear', suggestions: ['Clear', 'Edema', 'Scar', 'Opacity', 'Ulcer', 'Abrasion', 'KPs', 'Arcus', 'Punctate keratopathy', 'Ectasia'] },
    { key: 'anterior_chamber', label: 'Anterior Chamber', normal: 'Deep & quiet', suggestions: ['Deep & quiet', 'Shallow', 'Cells', 'Flare', 'Hyphema', 'Hypopyon'] },
    { key: 'iris', label: 'Iris', normal: 'Normal', suggestions: ['Normal', 'Rubeosis', 'Posterior synechiae', 'Atrophy', 'Peripheral iridotomy', 'Coloboma'] },
    { key: 'pupil', label: 'Pupil', normal: 'Round, regular, reactive', suggestions: ['Round, regular, reactive', 'Sluggish', 'RAPD', 'Fixed dilated', 'Irregular', 'Pharmacologically dilated'] },
    { key: 'lens', label: 'Lens', normal: 'Clear', suggestions: ['Clear', 'NS1', 'NS2', 'NS3', 'NS4', 'Cortical cataract', 'PSC', 'Mature cataract', 'Pseudophakia (PCIOL)', 'Aphakia', 'PCO'] },
];

export const IOP_ROWS: EyeRow[] = [
    { key: 'iop', label: 'IOP (mmHg)', type: 'number', step: 0.5, min: 0, max: 80, placeholder: '15' },
];

export const FUNDUS_ROWS: EyeRow[] = [
    { key: 'optic_disc', label: 'Optic Disc', normal: 'Pink, sharp margins', suggestions: ['Pink, sharp margins', 'Pale', 'Swollen', 'Cupped', 'Tilted', 'Disc hemorrhage'] },
    { key: 'cd_ratio', label: 'C/D Ratio', type: 'number', step: 0.05, min: 0, max: 1, placeholder: '0.3' },
    { key: 'macula', label: 'Macula', normal: 'Normal foveal reflex', suggestions: ['Normal foveal reflex', 'Drusen', 'Edema', 'Exudates', 'Hemorrhage', 'Scar', 'Macular hole', 'ERM'] },
    { key: 'retina', label: 'Retina', normal: 'Flat, attached', suggestions: ['Flat, attached', 'Dot-blot hemorrhages', 'Hard exudates', 'Cotton-wool spots', 'Detachment', 'Lattice degeneration', 'Laser scars'] },
    { key: 'vessels', label: 'Vessels', normal: 'Normal', suggestions: ['Normal', 'AV nipping', 'Attenuated', 'Tortuous', 'Neovascularization', 'Sheathing'] },
    { key: 'vitreous', label: 'Vitreous', normal: 'Clear', suggestions: ['Clear', 'PVD', 'Floaters', 'Hemorrhage', 'Cells', 'Asteroid hyalosis'] },
];

export const ALL_EYE_ROWS: EyeRow[] = [
    ...VISUAL_ACUITY_ROWS,
    ...REFRACTION_ROWS,
    ...ANTERIOR_SEGMENT_ROWS,
    ...IOP_ROWS,
    ...FUNDUS_ROWS,
];

export function emptyEyeFindings(): EyeFindings {
    return Object.fromEntries(ALL_EYE_ROWS.map((row) => [row.key, ''])) as EyeFindings;
}

export interface MedicationRow {
    name: string;
    dose: string;
    route: string;
    frequency: string;
    remarks: string;
}

export interface ExaminationEyeRecord extends Partial<EyeFindings> {
    id: string;
    eye: Eye;
}

export interface ExaminationDiagnosisRecord {
    id: string;
    diagnosis_id: string;
    eye: string | null;
    notes: string | null;
    diagnosis: { id: string; name: string; code: string | null } | null;
}

export interface ExaminationInvestigationRecord {
    id: string;
    service_id: string | null;
    name: string;
    eye: string | null;
    notes: string | null;
}

export interface ExaminationRecord {
    id: string;
    booking_id: string;
    doctor_id: string | null;
    status: 'draft' | 'finalized';
    chief_complaint: string | null;
    complaint_duration: string | null;
    affected_eye: string | null;
    eye_disease_history: string[] | null;
    eye_disease_notes: string | null;
    eye_surgery_history: string[] | null;
    eye_surgery_notes: string | null;
    eye_trauma: boolean | null;
    eye_trauma_notes: string | null;
    glasses_usage: string | null;
    contact_lenses: string | null;
    previous_eye_medications: string | null;
    allergies: string | null;
    systemic_diseases: string[] | null;
    history_notes: string | null;
    iop_method: string | null;
    assessment: string | null;
    treatment_plan: string | null;
    medications: MedicationRow[] | null;
    recommendations: string | null;
    follow_up: string | null;
    next_visit_date: string | null;
    examined_at: string;
    finalized_at: string | null;
    eyes: ExaminationEyeRecord[];
    diagnoses: ExaminationDiagnosisRecord[];
    investigations: ExaminationInvestigationRecord[];
    doctor: { id: string; name: string; specialty?: string | null } | null;
    finalized_by?: { id: number; name: string } | null;
    created_by?: { id: number; name: string } | null;
}

export interface PatientSummary {
    booking_id: string;
    patient_name: string;
    file_no: string;
    patient_age: number | null;
    gender: string | null;
    patient_phone: string | null;
    national_id: string | null;
    dept: string | null;
    dept_label: string | null;
    visit_date: string | null;
    eye_side: string | null;
    doctor: { id: string; name: string } | null;
}

export interface ExaminationOptions {
    eye_sides: Option[];
    eye_diseases: Option[];
    eye_surgeries: Option[];
    systemic_diseases: Option[];
    glasses_usage: Option[];
    contact_lenses: Option[];
    iop_methods: Option[];
    diagnoses: { id: string; name: string; code: string | null }[];
    investigations: { id: string; name: string; dept: string }[];
    doctors: { id: string; name: string }[];
}

export const GENDER_LABELS: Record<string, string> = {
    male: 'ذكر',
    female: 'أنثى',
};

export function optionLabel(options: Option[], value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return options.find((option) => option.value === value)?.label ?? value;
}

/** Format a refraction power the way it is written clinically: +1.25 / -0.50 / 0.00. */
export function formatPower(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const number = Number(value);

    if (Number.isNaN(number)) {
        return String(value);
    }

    return (number > 0 ? '+' : '') + number.toFixed(2);
}
