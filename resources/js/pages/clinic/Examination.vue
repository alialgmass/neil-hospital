<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, ChevronLeft, Copy, FileClock, Lock, Printer, Save, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Modal from '@/components/shared/Modal.vue';
import { NO_PERMISSION_TITLE, usePermissions } from '@/composables/usePermissions';
import { formatDate } from '@/lib/date';
import booking from '@/routes/booking';
import examinations from '@/routes/examinations';
import labs from '@/routes/labs';
import DiagnosisPicker from './Partials/DiagnosisPicker.vue';
import type { DiagnosisRow } from './Partials/DiagnosisPicker.vue';
import {
    ALL_EYE_ROWS,
    ANTERIOR_SEGMENT_ROWS,
    emptyEyeFindings,
    EYES,
    FUNDUS_ROWS,
    GENDER_LABELS,
    IOP_ROWS,
    REFRACTION_ROWS,
    VISUAL_ACUITY_ROWS,
} from './Partials/examination';
import type {
    Eye,
    EyeFindings,
    EyeRow,
    ExaminationOptions,
    ExaminationRecord,
    MedicationRow,
    PatientSummary,
} from './Partials/examination';
import ExamSection from './Partials/ExamSection.vue';
import EyeGrid from './Partials/EyeGrid.vue';
import InvestigationPicker from './Partials/InvestigationPicker.vue';
import type { InvestigationRow } from './Partials/InvestigationPicker.vue';
import MedicationsTable from './Partials/MedicationsTable.vue';

interface PreviousExamination {
    id: string;
    status: 'draft' | 'finalized';
    examined_at: string;
    booking: { id: string; file_no: string; visit_date: string; dept: string } | null;
    doctor: { id: string; name: string } | null;
    diagnoses: { id: string; eye: string | null; diagnosis: { id: string; name: string } | null }[];
}

interface Prefill {
    chief_complaint?: string;
    allergies?: string;
    eyes?: Partial<Record<Eye, Partial<EyeFindings>>>;
}

const props = defineProps<{
    patient: PatientSummary;
    examination: ExaminationRecord | null;
    read_only: boolean;
    prefill: Prefill | [];
    default_doctor_id: string | null;
    previous_examinations: PreviousExamination[];
    options: ExaminationOptions;
}>();

const { can } = usePermissions();

const isFinalized = computed(() => props.examination?.status === 'finalized');
const canEdit = computed(() =>
    props.examination ? can('examinations.update') : can('examinations.create'),
);
const readOnly = computed(() => props.read_only || isFinalized.value || !canEdit.value);
const canPrint = computed(() => !!props.examination && can('examinations.print'));
const canDelete = computed(() =>
    !!props.examination && !isFinalized.value && !props.read_only && can('examinations.delete'),
);

// ── Form state ──────────────────────────────────────────────────────────
const exam = props.examination;
const prefill: Prefill = Array.isArray(props.prefill) ? {} : props.prefill;

function eyeValues(eye: Eye): EyeFindings {
    const values = emptyEyeFindings();
    const source = exam?.eyes.find((record) => record.eye === eye) ?? prefill.eyes?.[eye] ?? {};

    for (const row of ALL_EYE_ROWS) {
        const value = source[row.key];
        values[row.key] = value === null || value === undefined ? '' : value;
    }

    return values;
}

function medicationRows(rows?: MedicationRow[] | null): MedicationRow[] {
    return rows && rows.length > 0 ? rows : [{ name: '', dose: '', route: '', frequency: '', remarks: '' }];
}

const form = useForm({
    doctor_id: exam?.doctor_id ?? props.default_doctor_id ?? '',
    chief_complaint: exam?.chief_complaint ?? prefill.chief_complaint ?? '',
    complaint_duration: exam?.complaint_duration ?? '',
    affected_eye: exam?.affected_eye ?? props.patient.eye_side ?? '',
    eye_disease_history: exam?.eye_disease_history ?? ([] as string[]),
    eye_disease_notes: exam?.eye_disease_notes ?? '',
    eye_surgery_history: exam?.eye_surgery_history ?? ([] as string[]),
    eye_surgery_notes: exam?.eye_surgery_notes ?? '',
    eye_trauma: exam?.eye_trauma ?? (null as boolean | null),
    eye_trauma_notes: exam?.eye_trauma_notes ?? '',
    glasses_usage: exam?.glasses_usage ?? '',
    contact_lenses: exam?.contact_lenses ?? '',
    previous_eye_medications: exam?.previous_eye_medications ?? '',
    allergies: exam?.allergies ?? prefill.allergies ?? '',
    systemic_diseases: exam?.systemic_diseases ?? ([] as string[]),
    history_notes: exam?.history_notes ?? '',
    eyes: { OD: eyeValues('OD'), OS: eyeValues('OS') } as Record<Eye, EyeFindings>,
    iop_method: exam?.iop_method ?? '',
    diagnoses: (exam?.diagnoses ?? []).map((row): DiagnosisRow => ({
        diagnosis_id: row.diagnosis_id,
        name: row.diagnosis?.name ?? '',
        eye: row.eye ?? '',
        notes: row.notes ?? '',
    })),
    investigations: (exam?.investigations ?? [])
        .filter((row) => row.service_id)
        .map((row): InvestigationRow => ({ service_id: row.service_id as string, eye: row.eye ?? '', notes: row.notes ?? '' })),
    assessment: exam?.assessment ?? '',
    treatment_plan: exam?.treatment_plan ?? '',
    medications: medicationRows(exam?.medications),
    recommendations: exam?.recommendations ?? '',
    follow_up: exam?.follow_up ?? '',
    next_visit_date: exam?.next_visit_date ? exam.next_visit_date.slice(0, 10) : '',
});

const errors = computed(() => form.errors as Record<string, string | undefined>);

// Investigations whose service was deleted can't be re-selected — keep them visible read-only.
const orphanInvestigations = computed(() => (props.examination?.investigations ?? []).filter((row) => !row.service_id));

// ── Quick-entry helpers ─────────────────────────────────────────────────
function fillNormal(rows: EyeRow[], eye: Eye): void {
    for (const row of rows) {
        if (row.normal && form.eyes[eye][row.key] === '') {
            form.eyes[eye][row.key] = row.normal;
        }
    }
}

function copyOdToOs(rows: EyeRow[]): void {
    for (const row of rows) {
        form.eyes.OS[row.key] = form.eyes.OD[row.key];
    }
}

function toggleInList(list: string[], value: string): void {
    const index = list.indexOf(value);

    if (index >= 0) {
        list.splice(index, 1);
    } else {
        list.push(value);
    }
}

// ── Submit / finalize / delete ──────────────────────────────────────────
const confirmFinalize = ref(false);
const confirmDelete = ref(false);

function submit(finalize: boolean): void {
    if (readOnly.value) {
        return;
    }

    confirmFinalize.value = false;

    form.transform((data) => ({
        ...data,
        finalize,
        diagnoses: data.diagnoses
            .filter((row) => row.name.trim() !== '')
            .map((row) => ({ ...row, name: row.name.trim() })),
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
        onError: () => {
            document.querySelector('.form-error, .border-hospital-danger')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
    };

    if (props.examination) {
        form.put(examinations.update(props.examination.id).url, options);
    } else {
        form.post(examinations.store(props.patient.booking_id).url, options);
    }
}

function destroyExamination(): void {
    if (!props.examination) {
        return;
    }

    router.delete(examinations.destroy(props.examination.id).url, {
        preserveState: false,
        onFinish: () => {
            confirmDelete.value = false;
        },
    });
}

// ── Navigation ──────────────────────────────────────────────────────────
/** The examination is done from the Labs queue — return there on the visit's day. */
const isLabsVisit = computed(() => props.patient.dept === 'labs');

const backUrl = computed(() =>
    isLabsVisit.value
        ? labs.index({ query: { date: props.patient.visit_date ?? undefined } }).url
        : booking.patientFile(props.patient.file_no).url,
);

const sections = [
    { id: 'patient', label: 'المريض' },
    { id: 'complaint', label: 'الشكوى' },
    { id: 'history', label: 'التاريخ المرضي' },
    { id: 'va', label: 'حدة الإبصار' },
    { id: 'refraction', label: 'Refraction' },
    { id: 'anterior', label: 'Anterior' },
    { id: 'iop', label: 'IOP' },
    { id: 'fundus', label: 'Fundus' },
    { id: 'diagnosis', label: 'التشخيص' },
    { id: 'investigations', label: 'الفحوصات' },
    { id: 'plan', label: 'الخطة العلاجية' },
    { id: 'follow-up', label: 'المتابعة' },
];

const statusLabel = computed(() => {
    if (!props.examination) {
        return 'فحص جديد';
    }

    return isFinalized.value ? 'معتمد' : 'مسودة';
});

const statusClass = computed(() => {
    if (!props.examination) {
        return 'bg-hospital-bg text-hospital-text-2';
    }

    return isFinalized.value
        ? 'bg-hospital-success-pale text-hospital-success'
        : 'bg-hospital-warning-pale text-hospital-warning';
});

function eyeBadge(eye: string | null): string {
    return eye ? ` (${eye})` : '';
}
</script>

<template>
    <div>
        <Head :title="`الفحص الطبي — ${patient.patient_name}`" />

        <!-- Top bar -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a :href="backUrl" class="flex items-center gap-1 text-sm font-medium text-hospital-primary hover:underline">
                    <ChevronLeft class="h-4 w-4" />
                    {{ isLabsVisit ? 'العودة لقسم الفحوصات' : 'العودة لملف المريض' }}
                </a>
                <h1 class="text-lg font-bold text-hospital-text">الفحص الطبي — Medical Examination</h1>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="statusClass">{{ statusLabel }}</span>
            </div>
            <a
                v-if="canPrint && examination"
                :href="examinations.print(examination.id).url"
                target="_blank"
                class="btn-secondary gap-2"
            >
                <Printer class="h-4 w-4" />
                طباعة التقرير
            </a>
        </div>

        <!-- Read-only banner -->
        <div
            v-if="readOnly"
            class="mb-4 flex items-center gap-2 rounded-lg border border-hospital-border bg-hospital-bg px-4 py-2.5 text-sm text-hospital-text-2"
        >
            <Lock class="h-4 w-4 shrink-0" />
            <span v-if="isFinalized">
                هذا الفحص معتمد بتاريخ {{ examination?.finalized_at ? formatDate(examination.finalized_at) : '' }}
                <template v-if="examination?.finalized_by"> بواسطة {{ examination.finalized_by.name }}</template>
                — للعرض فقط.
            </span>
            <span v-else-if="read_only">عرض فحص سابق — للقراءة فقط.</span>
            <span v-else>ليس لديك صلاحية {{ examination ? 'تعديل' : 'إنشاء' }} الفحص الطبي — للعرض فقط.</span>
            <a
                v-if="read_only && !isFinalized && examination"
                :href="examinations.booking(examination.booking_id).url"
                class="ms-auto text-xs font-semibold text-hospital-primary hover:underline"
            >فتح للتعديل</a>
        </div>

        <div v-if="errors.booking" class="mb-4 rounded-lg border border-hospital-danger bg-hospital-danger-pale px-4 py-2.5 text-sm text-hospital-danger">
            {{ errors.booking }}
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
            <!-- ═══════════ Main column ═══════════ -->
            <div class="min-w-0 space-y-4">
                <!-- Section navigation -->
                <nav class="sticky top-0 z-10 -mx-1 flex gap-1.5 overflow-x-auto rounded-xl border border-hospital-border bg-hospital-surface/95 p-1.5 backdrop-blur print:hidden">
                    <a
                        v-for="section in sections"
                        :key="section.id"
                        :href="`#${section.id}`"
                        class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-medium text-hospital-text-2 hover:bg-hospital-primary-pale hover:text-hospital-primary"
                    >{{ section.label }}</a>
                </nav>

                <!-- 1. Patient information (read from the booking) -->
                <ExamSection id="patient" :number="1" title="بيانات المريض" subtitle="Patient Information — تُقرأ تلقائيًا من الحجز">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                        <div>
                            <dt class="text-[11px] text-hospital-text-3">الاسم</dt>
                            <dd class="font-bold text-hospital-text">{{ patient.patient_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-hospital-text-3">رقم الملف</dt>
                            <dd class="font-bold text-hospital-primary" dir="ltr" style="text-align: start">{{ patient.file_no }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-hospital-text-3">السن</dt>
                            <dd>{{ patient.patient_age ? `${patient.patient_age} سنة` : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-hospital-text-3">النوع</dt>
                            <dd>{{ patient.gender ? GENDER_LABELS[patient.gender] ?? patient.gender : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-hospital-text-3">الهاتف</dt>
                            <dd dir="ltr" style="text-align: start">{{ patient.patient_phone || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-hospital-text-3">رقم الحجز / القسم</dt>
                            <dd dir="ltr" style="text-align: start">{{ patient.file_no }} · {{ patient.dept_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-hospital-text-3">تاريخ الزيارة</dt>
                            <dd>{{ patient.visit_date ? formatDate(patient.visit_date) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-hospital-text-3">الطبيب الفاحص</dt>
                            <dd>
                                <select v-model="form.doctor_id" :disabled="readOnly" class="input-field py-1 text-sm" aria-label="الطبيب الفاحص">
                                    <option value="">— {{ patient.doctor?.name ?? 'غير محدد' }} —</option>
                                    <option v-for="doctor in options.doctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }}</option>
                                </select>
                            </dd>
                        </div>
                    </dl>
                </ExamSection>

                <fieldset :disabled="readOnly" class="min-w-0 space-y-4">
                    <!-- 2. Chief complaint -->
                    <ExamSection id="complaint" :number="2" title="الشكوى الرئيسية" subtitle="Chief Complaint">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-hospital-text-2">
                                    الشكوى <span class="text-hospital-text-3">(مطلوبة للاعتماد)</span>
                                </label>
                                <textarea v-model="form.chief_complaint" rows="2" class="input-field resize-none" :class="errors.chief_complaint ? 'border-hospital-danger' : ''" />
                                <p v-if="errors.chief_complaint" class="form-error">{{ errors.chief_complaint }}</p>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">المدة (Duration)</label>
                                    <input v-model="form.complaint_duration" type="text" list="duration-suggestions" placeholder="مثال: 3 أيام" class="input-field" />
                                    <datalist id="duration-suggestions">
                                        <option value="يوم" /><option value="3 أيام" /><option value="أسبوع" /><option value="أسبوعين" />
                                        <option value="شهر" /><option value="3 شهور" /><option value="6 شهور" /><option value="سنة" /><option value="أكثر من سنة" />
                                    </datalist>
                                </div>
                                <div>
                                    <p class="mb-1 block text-xs font-medium text-hospital-text-2">العين المصابة</p>
                                    <div class="flex gap-1.5">
                                        <label
                                            v-for="side in options.eye_sides"
                                            :key="side.value"
                                            class="flex flex-1 cursor-pointer flex-col items-center rounded-lg border px-2 py-1.5 text-center transition-colors has-[:checked]:border-hospital-primary has-[:checked]:bg-hospital-primary-pale"
                                            :class="readOnly ? 'cursor-default' : ''"
                                        >
                                            <input v-model="form.affected_eye" type="radio" :value="side.value" class="sr-only" />
                                            <span class="text-sm font-extrabold text-hospital-text">{{ side.value }}</span>
                                            <span class="text-[10px] text-hospital-text-3">{{ side.label }}</span>
                                        </label>
                                    </div>
                                    <p v-if="errors.affected_eye" class="form-error">{{ errors.affected_eye }}</p>
                                </div>
                            </div>
                        </div>
                    </ExamSection>

                    <!-- 3. Ophthalmic history -->
                    <ExamSection id="history" :number="3" title="التاريخ المرضي للعين" subtitle="Ophthalmic History">
                        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                            <div>
                                <p class="mb-2 text-xs font-semibold text-hospital-text-2">أمراض عين سابقة</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="option in options.eye_diseases"
                                        :key="option.value"
                                        type="button"
                                        class="rounded-full border px-2.5 py-1 text-[11px] transition-colors"
                                        :class="form.eye_disease_history.includes(option.value) ? 'border-hospital-primary bg-hospital-primary text-white' : 'border-hospital-border text-hospital-text-2 hover:border-hospital-primary'"
                                        :aria-pressed="form.eye_disease_history.includes(option.value)"
                                        @click="toggleInList(form.eye_disease_history, option.value)"
                                    >{{ option.label }}</button>
                                </div>
                                <input v-model="form.eye_disease_notes" type="text" placeholder="أمراض أخرى / تفاصيل" class="input-field mt-2" />
                            </div>
                            <div>
                                <p class="mb-2 text-xs font-semibold text-hospital-text-2">عمليات عين سابقة</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="option in options.eye_surgeries"
                                        :key="option.value"
                                        type="button"
                                        class="rounded-full border px-2.5 py-1 text-[11px] transition-colors"
                                        :class="form.eye_surgery_history.includes(option.value) ? 'border-hospital-primary bg-hospital-primary text-white' : 'border-hospital-border text-hospital-text-2 hover:border-hospital-primary'"
                                        :aria-pressed="form.eye_surgery_history.includes(option.value)"
                                        @click="toggleInList(form.eye_surgery_history, option.value)"
                                    >{{ option.label }}</button>
                                </div>
                                <input v-model="form.eye_surgery_notes" type="text" placeholder="العين / التاريخ / تفاصيل" class="input-field mt-2" />
                            </div>
                            <div>
                                <p class="mb-2 text-xs font-semibold text-hospital-text-2">إصابات عين سابقة (Trauma)</p>
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-1.5 text-xs"><input v-model="form.eye_trauma" type="radio" :value="false" /> لا</label>
                                    <label class="flex items-center gap-1.5 text-xs"><input v-model="form.eye_trauma" type="radio" :value="true" /> نعم</label>
                                    <input v-if="form.eye_trauma" v-model="form.eye_trauma_notes" type="text" placeholder="نوع الإصابة / العين / التاريخ" class="input-field flex-1" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-hospital-text-2">النظارة</label>
                                    <select v-model="form.glasses_usage" class="input-field">
                                        <option value="">— غير محدد —</option>
                                        <option v-for="option in options.glasses_usage" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-hospital-text-2">العدسات اللاصقة</label>
                                    <select v-model="form.contact_lenses" class="input-field">
                                        <option value="">— غير محدد —</option>
                                        <option v-for="option in options.contact_lenses" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">أدوية عين سابقة / حالية</label>
                                <input v-model="form.previous_eye_medications" type="text" placeholder="مثال: Timolol, Xalatan…" dir="auto" class="input-field" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-hospital-danger">حساسية من أدوية / قطرات</label>
                                <input v-model="form.allergies" type="text" placeholder="لا يوجد / اذكر الدواء" dir="auto" class="input-field" />
                            </div>
                            <div class="lg:col-span-2">
                                <p class="mb-2 text-xs font-semibold text-hospital-text-2">أمراض عامة ذات صلة</p>
                                <div class="flex flex-wrap gap-x-4 gap-y-2">
                                    <label v-for="option in options.systemic_diseases" :key="option.value" class="flex items-center gap-1.5 text-xs text-hospital-text">
                                        <input v-model="form.systemic_diseases" type="checkbox" :value="option.value" class="rounded border-hospital-border" />
                                        {{ option.label }}
                                    </label>
                                </div>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">ملاحظات أخرى</label>
                                <textarea v-model="form.history_notes" rows="2" class="input-field resize-none" />
                            </div>
                        </div>
                    </ExamSection>

                    <!-- 4. Visual acuity -->
                    <ExamSection id="va" :number="4" title="حدة الإبصار" subtitle="Visual Acuity">
                        <template v-if="!readOnly" #actions>
                            <button type="button" class="btn-secondary gap-1 px-2.5 py-1 text-xs" @click="copyOdToOs(VISUAL_ACUITY_ROWS)"><Copy class="h-3.5 w-3.5" />نسخ OD → OS</button>
                        </template>
                        <EyeGrid v-model:eyes="form.eyes" :rows="VISUAL_ACUITY_ROWS" :errors="errors" :read-only="readOnly" id-prefix="va" />
                    </ExamSection>

                    <!-- 5. Refraction -->
                    <ExamSection id="refraction" :number="5" title="قياس النظر" subtitle="Refraction — Sphere / Cylinder بمضاعفات 0.25">
                        <template v-if="!readOnly" #actions>
                            <button type="button" class="btn-secondary gap-1 px-2.5 py-1 text-xs" @click="copyOdToOs(REFRACTION_ROWS)"><Copy class="h-3.5 w-3.5" />نسخ OD → OS</button>
                        </template>
                        <EyeGrid v-model:eyes="form.eyes" :rows="REFRACTION_ROWS" :errors="errors" :read-only="readOnly" id-prefix="rx" />
                    </ExamSection>

                    <!-- 6. Anterior segment -->
                    <ExamSection id="anterior" :number="6" title="فحص الجزء الأمامي" subtitle="External / Anterior Segment Examination">
                        <template v-if="!readOnly" #actions>
                            <button v-for="eye in EYES" :key="eye" type="button" class="btn-secondary gap-1 px-2.5 py-1 text-xs" @click="fillNormal(ANTERIOR_SEGMENT_ROWS, eye)">
                                <Sparkles class="h-3.5 w-3.5" />{{ eye }} طبيعي
                            </button>
                            <button type="button" class="btn-secondary gap-1 px-2.5 py-1 text-xs" @click="copyOdToOs(ANTERIOR_SEGMENT_ROWS)"><Copy class="h-3.5 w-3.5" />نسخ OD → OS</button>
                        </template>
                        <EyeGrid v-model:eyes="form.eyes" :rows="ANTERIOR_SEGMENT_ROWS" :errors="errors" :read-only="readOnly" id-prefix="ant" />
                    </ExamSection>

                    <!-- 7. IOP -->
                    <ExamSection id="iop" :number="7" title="ضغط العين" subtitle="Intraocular Pressure (IOP)">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                            <EyeGrid v-model:eyes="form.eyes" :rows="IOP_ROWS" :errors="errors" :read-only="readOnly" id-prefix="iop" />
                            <div>
                                <p class="mb-2 text-xs font-semibold text-hospital-text-2">طريقة القياس (Method)</p>
                                <div class="space-y-1.5">
                                    <label v-for="option in options.iop_methods" :key="option.value" class="flex items-center gap-2 text-sm text-hospital-text" dir="ltr">
                                        <input v-model="form.iop_method" type="radio" :value="option.value" />
                                        {{ option.label }}
                                    </label>
                                </div>
                                <p v-if="errors.iop_method" class="form-error">{{ errors.iop_method }}</p>
                            </div>
                        </div>
                    </ExamSection>

                    <!-- 8. Fundus -->
                    <ExamSection id="fundus" :number="8" title="فحص قاع العين" subtitle="Fundus Examination — C/D Ratio من 0 إلى 1">
                        <template v-if="!readOnly" #actions>
                            <button v-for="eye in EYES" :key="eye" type="button" class="btn-secondary gap-1 px-2.5 py-1 text-xs" @click="fillNormal(FUNDUS_ROWS, eye)">
                                <Sparkles class="h-3.5 w-3.5" />{{ eye }} طبيعي
                            </button>
                            <button type="button" class="btn-secondary gap-1 px-2.5 py-1 text-xs" @click="copyOdToOs(FUNDUS_ROWS)"><Copy class="h-3.5 w-3.5" />نسخ OD → OS</button>
                        </template>
                        <EyeGrid v-model:eyes="form.eyes" :rows="FUNDUS_ROWS" :errors="errors" :read-only="readOnly" id-prefix="fundus" />
                    </ExamSection>

                    <!-- 9. Diagnosis -->
                    <ExamSection id="diagnosis" :number="9" title="التشخيص" subtitle="Diagnosis — يمكن اختيار أكثر من تشخيص مع تحديد العين">
                        <DiagnosisPicker
                            v-model="form.diagnoses"
                            :catalog="options.diagnoses"
                            :eye-options="options.eye_sides"
                            :errors="errors"
                            :read-only="readOnly"
                        />
                        <p v-if="errors.diagnoses" class="form-error">{{ errors.diagnoses }}</p>
                    </ExamSection>

                    <!-- 10. Investigations -->
                    <ExamSection id="investigations" :number="10" title="الفحوصات المطلوبة" subtitle="Investigations — من خدمات الفحوصات والبنتكام">
                        <InvestigationPicker
                            v-model="form.investigations"
                            :services="options.investigations"
                            :eye-options="options.eye_sides"
                            :default-eye="form.affected_eye || null"
                            :read-only="readOnly"
                        />
                        <ul v-if="orphanInvestigations.length" class="mt-2 space-y-1 text-xs text-hospital-text-3">
                            <li v-for="row in orphanInvestigations" :key="row.id" dir="ltr" style="text-align: start">
                                {{ row.name }}{{ eyeBadge(row.eye) }} — خدمة محذوفة
                            </li>
                        </ul>
                        <p v-for="(message, key) in errors" v-show="String(key).startsWith('investigations')" :key="key" class="form-error">{{ message }}</p>
                    </ExamSection>

                    <!-- 11. Assessment & treatment -->
                    <ExamSection id="plan" :number="11" title="التقييم والخطة العلاجية" subtitle="Assessment & Treatment Plan">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">التقييم (Assessment)</label>
                                <textarea v-model="form.assessment" rows="3" dir="auto" class="input-field resize-none" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">الخطة العلاجية (Treatment Plan)</label>
                                <textarea v-model="form.treatment_plan" rows="3" dir="auto" class="input-field resize-none" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">الأدوية (Medications)</label>
                                <MedicationsTable v-model="form.medications" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">التوصيات (Recommendations)</label>
                                <textarea v-model="form.recommendations" rows="2" dir="auto" class="input-field resize-none" />
                            </div>
                        </div>
                    </ExamSection>

                    <!-- 12. Follow-up -->
                    <ExamSection id="follow-up" :number="12" title="المتابعة" subtitle="Follow-up">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">المتابعة</label>
                                <input v-model="form.follow_up" type="text" list="follow-up-suggestions" placeholder="مثال: بعد أسبوعين" class="input-field" />
                                <datalist id="follow-up-suggestions">
                                    <option value="بعد 3 أيام" /><option value="بعد أسبوع" /><option value="بعد أسبوعين" />
                                    <option value="بعد شهر" /><option value="بعد 3 شهور" /><option value="بعد 6 شهور" /><option value="عند اللزوم" />
                                </datalist>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">موعد الزيارة القادمة</label>
                                <input v-model="form.next_visit_date" type="date" :min="patient.visit_date ?? undefined" class="input-field" :class="errors.next_visit_date ? 'border-hospital-danger' : ''" />
                                <p v-if="errors.next_visit_date" class="form-error">{{ errors.next_visit_date }}</p>
                            </div>
                        </div>
                    </ExamSection>
                </fieldset>

                <!-- Sticky action bar -->
                <div
                    v-if="!readOnly"
                    class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-hospital-border bg-hospital-surface/95 p-3 shadow-lg backdrop-blur"
                >
                    <div class="flex items-center gap-2 text-xs text-hospital-text-3">
                        <span v-if="form.isDirty" class="font-semibold text-hospital-warning">تغييرات غير محفوظة</span>
                        <span v-else-if="form.recentlySuccessful" class="text-hospital-success">تم الحفظ</span>
                        <button
                            v-if="canDelete"
                            type="button"
                            class="flex items-center gap-1 rounded-lg px-2 py-1 font-medium text-hospital-danger hover:bg-hospital-danger-pale"
                            @click="confirmDelete = true"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            حذف المسودة
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-secondary gap-2" :disabled="form.processing" @click="submit(false)">
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'جارٍ الحفظ…' : 'حفظ كمسودة' }}
                        </button>
                        <button
                            type="button"
                            class="btn-primary gap-2"
                            :disabled="form.processing || !can('examinations.update')"
                            :title="can('examinations.update') ? undefined : NO_PERMISSION_TITLE"
                            @click="confirmFinalize = true"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            حفظ واعتماد
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════ Side column ═══════════ -->
            <aside class="space-y-4">
                <div class="rounded-xl border border-hospital-border bg-hospital-surface p-4 shadow-sm">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-hospital-text">
                        <FileClock class="h-4 w-4 text-hospital-primary" />
                        الفحوصات السابقة
                    </h3>
                    <p v-if="previous_examinations.length === 0" class="text-xs text-hospital-text-3">لا توجد فحوصات سابقة لهذا المريض</p>
                    <ul v-else class="space-y-2">
                        <li v-for="previous in previous_examinations" :key="previous.id">
                            <a
                                :href="examinations.show(previous.id).url"
                                class="block rounded-lg border border-hospital-border p-2.5 text-xs transition-colors hover:border-hospital-primary hover:bg-hospital-bg"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-hospital-primary">{{ formatDate(previous.examined_at) }}</span>
                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="previous.status === 'finalized' ? 'bg-hospital-success-pale text-hospital-success' : 'bg-hospital-warning-pale text-hospital-warning'"
                                    >{{ previous.status === 'finalized' ? 'معتمد' : 'مسودة' }}</span>
                                </div>
                                <p class="mt-1 text-hospital-text-3">{{ previous.doctor?.name ?? '—' }} · {{ previous.booking?.file_no }}</p>
                                <p v-if="previous.diagnoses.length" class="mt-1 line-clamp-2 text-hospital-text-2" dir="ltr" style="text-align: start">
                                    {{ previous.diagnoses.map((d) => `${d.diagnosis?.name ?? ''}${eyeBadge(d.eye)}`).join(' · ') }}
                                </p>
                            </a>
                        </li>
                    </ul>
                </div>

                <div v-if="examination" class="rounded-xl border border-hospital-border bg-hospital-surface p-4 text-xs text-hospital-text-2 shadow-sm">
                    <p>تاريخ الفحص: <strong>{{ formatDate(examination.examined_at) }}</strong></p>
                    <p v-if="examination.created_by" class="mt-1">أنشأه: {{ examination.created_by.name }}</p>
                    <p v-if="examination.finalized_by" class="mt-1">اعتمده: {{ examination.finalized_by.name }}</p>
                </div>
            </aside>
        </div>

        <!-- Finalize confirmation -->
        <Modal v-model="confirmFinalize" title="اعتماد الفحص الطبي" size="sm">
            <p class="text-sm text-hospital-text-2">
                بعد الاعتماد يصبح الفحص جزءًا من الملف الطبي ولا يمكن تعديله أو حذفه. يُشترط وجود الشكوى الرئيسية وتشخيص واحد على الأقل.
            </p>
            <template #footer>
                <button type="button" class="btn-secondary" @click="confirmFinalize = false">إلغاء</button>
                <button type="button" class="btn-primary" :disabled="form.processing" @click="submit(true)">اعتماد</button>
            </template>
        </Modal>

        <!-- Delete confirmation -->
        <Modal v-model="confirmDelete" title="حذف مسودة الفحص" size="sm">
            <p class="text-sm text-hospital-text-2">سيتم حذف مسودة الفحص وكل بياناتها نهائيًا.</p>
            <template #footer>
                <button type="button" class="btn-secondary" @click="confirmDelete = false">إلغاء</button>
                <button type="button" class="btn-danger" @click="destroyExamination">حذف</button>
            </template>
        </Modal>
    </div>
</template>
